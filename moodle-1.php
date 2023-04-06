<?php
require_once('lib/simple_html_dom.php');

class Moodle {
    const URL_HOME = '/my/';
    const URL_LOGIN = '/login/index.php';
    const URL_COURSES = '/lib/ajax/service.php?sesskey={sessKey}&info=local_subcourses_get_enrolled_courses_without_subcourses';
    const URL_ATTENDEES = '/user/index.php?id={courseId}';
    const URL_ATTENDEE_DETAIL = '/user/view.php?id={attendeeId}&course={courseId}&showallcourses=1';

    private $baseUrl = '';
    private $username = '';
    private $password = '';
    private $csrfToken = '';
    private $cookie = 'fake=cookie';
    private $sesskey = '';

    // Default headers
    private $headers = [
        'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        'accept-encoding: identity',
        'accept-language: en-US,en;q=0.9',
        'cache-control: no-cache',
        'pragma: no-cache',
        'sec-ch-ua: "Google Chrome";v="105", "Not)A;Brand";v="8", "Chromium";v="105"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Linux"',
        'sec-fetch-dest: document',
        'sec-fetch-mode: navigate',
        'sec-fetch-site: none',
        'sec-fetch-user: ?1',
        'upgrade-insecure-requests: 1',
        'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/105.0.0.0 Safari/537.36',
    ];


    public function __construct($baseUrl, $username, $password) {
        $this->baseUrl = $baseUrl;
        $this->username = $username;
        $this->password = $password;
    }


    // GET
    private function getUrl($url) {
        $getHeaders = array_merge(
            $this->headers, [
                'cookie: ' . $this->cookie,
            ]
        );

        $headerBuffer = implode("\r\n", $getHeaders);

        $opts = [
            'ssl'=> [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'proxy' => 'tcp://127.0.0.1:8080',
                'request_fulluri' => true,
                'method' => 'GET',
                'follow_location' => false,
                'header' => $headerBuffer
            ]
        ];

        return $this->makeHttpRequest($opts, $url);
    }


    // POST
    private function postUrl($url, $parameters) {
        $query = http_build_query($parameters);

        $postHeaders = array_merge(
            $this->headers, [
                'cookie: ' . $this->cookie,
                'content-type: application/x-www-form-urlencoded',
                'content-length: '. strlen($query)
            ]
        );

        $headerBuffer = implode("\r\n", $postHeaders);

        $opts = [
            'ssl'=> [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'proxy' => 'tcp://127.0.0.1:8080',
                'request_fulluri' => true,
                'method' => 'POST',
                'follow_location' => false,
                'header' => $headerBuffer,
                'content' => $query
            ]
        ];

        return $this->makeHttpRequest($opts, $url);
    }


    // Json POST
    private function jpostUrl($url, $json) {

        $postHeaders = array_merge(
            $this->headers, [
                'cookie: ' . $this->cookie,
                'content-type: application/json',
                'content-length: '. strlen($json)
            ]
        );

        $headerBuffer = implode("\r\n", $postHeaders);

        $opts = [
            'ssl'=> [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'proxy' => 'tcp://127.0.0.1:8080',
                'request_fulluri' => true,
                'method' => 'POST',
                'follow_location' => false,
                'header' => $headerBuffer,
                'content' => $json
            ]
        ];

        return $this->makeHttpRequest($opts, $url);
    }


    private function makeHttpRequest($opts, $url) {
        // PHP Stream
        $context = stream_context_create($opts);
        $buffer = file_get_contents($url, FALSE, $context);

        // Parse the cookies, if any
        $this->getCookie($http_response_header);

        return ([
            'headers' => $http_response_header,
            'content' => $buffer
        ]);
    }


    private function getCookie($headers) {
        foreach ($headers as $header) {
            if (preg_match('|^Set-Cookie: (MoodleSession=.*);|iU', $header, $matches)) {
                $this->cookie = $matches[1];
                break;
            }
        }
    }


    private function getSesskey($buffer) {
        if (preg_match('/"sesskey":"(.*)"/mU', $buffer, $matches)) {
            $this->sesskey = $matches[1];
            return $matches[1];
        }
    }


    private function cloudFlareDecodeEmail($encodedString){
        $k = hexdec(substr($encodedString,0,2));
        for($i=2,$email='';$i<strlen($encodedString)-1;$i+=2){
          $email.=chr(hexdec(substr($encodedString,$i,2))^$k);
        }
        return $email;
      }


    public function login() {
        // Get Anti-Cross-Site-Request-Forgery TOKEN
        $http = $this->getUrl($this->baseUrl . self::URL_LOGIN);
        $dom = new simple_html_dom();
        $dom->load($http['content']);
        // cerca il primo <input name="logintoken" value="xxx"> e prende xxx
        $loginToken = $dom->find('input[name=logintoken]', 0)->value;

        // Login
        $http = $this->postUrl($this->baseUrl . self::URL_LOGIN, [
            'anchor' => '',
            'logintoken' => $loginToken,
            'username' => $this->username,
            'password' => $this->password
        ]);

        // Test LogIn
        $http = $this->getUrl($this->baseUrl . self::URL_HOME);
        $dom = new simple_html_dom();
        $dom->load($http['content']);
        // cerca <a> dentro il <div class="user_set_header">
        $loginInfo = $dom->find('div.user_set_header img', 0);
        // se lo trova recupera l'atributo "alt" di <img> e ritorna true
        if ($loginInfo) {
            print('Logged in as: ' . $loginInfo->alt . PHP_EOL);
            $this->getSesskey($http['content']);
            return true;
        }

        // se non trova il div ritorna false
        print('Unable to login' . PHP_EOL);
        return false;
    }


    public function getCourses() {
        $myCourses = [];

        $json = '[{"index":0,"methodname":"local_subcourses_get_enrolled_courses_without_subcourses","args":{"offset":0,"limit":0,"classification":"all","sort":"fullname"}}]';

        $http = $this->jpostUrl(
            $this->baseUrl . str_replace('{sessKey}', $this->sesskey, self::URL_COURSES),
            $json
        );

        return json_decode($http['content']);
    }


    public function getAttendees($courseId) {
        $courseAttendees = [];

        $http = $this->getUrl($this->baseUrl . str_replace('{courseId}', $courseId, self::URL_ATTENDEES));
        $dom = new simple_html_dom();
        $dom->load($http['content']);
        $table = $dom->find('table#participants tbody', 0);
        if ($table) {
            $rows = $table->find('tr');
            if ($rows) {
                foreach ($rows as $row) {
                    $attendee = $row->find('th a', 0);
                    if ($attendee) {
                        $scrabledEmail = $row->find('td a', 0)->{'data-cfemail'};
                        $plaintextEmail = $this->cloudFlareDecodeEmail($scrabledEmail);

                        array_push($courseAttendees, [
                            'attendeeName' => $row->find('th a', 0)->plaintext,
                            'attendeeEmail' => $plaintextEmail,
                            'attendeeUrl' => html_entity_decode($row->find('th a', 0)->href),
                            'attendeeImage' => $row->find('th a img', 0)->src,
                            'attendeeDepartment' => trim($row->find('td', 2)->plaintext),
                            'attendeeRole' => trim($row->find('td', 3)->plaintext),
                            'attendeeGroups' => trim($row->find('td', 4)->plaintext),
                            'attendeeLastAccess' => trim($row->find('td', 5)->plaintext),
                        ]);
                    }
                }
            }
        }

        return $courseAttendees;
    }


    public function getAttendeeDetail($attendeeId, $courseId) {
        $http = $this->getUrl($this->baseUrl . 
            str_replace(
                '{attendeeId}',
                $attendeeId,
                str_replace('{courseId}', $courseId, self::URL_ATTENDEE_DETAIL)
            )
        );

        $dom = new simple_html_dom();
        $dom->load($http['content']);

        $attendeeDetails = [];

        $attendeeNodes = $dom->find('div.siderbar_contact_widget', 0)->find('p');
        if ($attendeeNodes) {
            for ($i=0; $i<=count($attendeeNodes); $i++) {
                $key = strtolower($dom->find('div.siderbar_contact_widget p', $i)->plaintext);
                $value = $dom->find('div.siderbar_contact_widget i', $i)->plaintext;
                if (preg_match('/\[email&/', $value)) {
                    $scrabledEmail = $dom->find('div.siderbar_contact_widget i', $i)->find('a', 0)->{'data-cfemail'};
                    $value = $this->cloudFlareDecodeEmail($scrabledEmail);
                }
                $attendeeDetails[$key] = $value;
            }

            $courses = [];
            $domCourses = $dom->find('div#panel-coursedetails ul li');
            foreach ($domCourses as $domCourse) {
                array_push(
                    $courses, 
                    [
                        'courseUrl' => $domCourse->find('a', 0)->plaintext,
                        'courseName' => $domCourse->find('a', 0)->href,
                    ]
                );
            }

            $attendeeDetails['courses'] = $courses;
        }

        return $attendeeDetails;
    }
}
