<?php
require_once('lib/simple_html_dom.php');

class Moodle {
    const URL_HOME = '/';
    const URL_LOGIN = '/login/index.php';
    const URL_ATTENDEES = '/user/index.php?id={courseId}';
    const URL_ATTENDEE_DETAIL = '/user/view.php?id={attendeeId}&course={courseId}';

    private $baseUrl = '';
    private $username = '';
    private $password = '';
    private $csrfToken = '';
    private $cookie = 'fake=cookie';

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
                // 'verify_peer' => false,
                // 'verify_peer_name' => false,
            ],
            'http' => [
                // 'proxy' => 'tcp://127.0.0.1:8080',
                // 'request_fulluri' => true,
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
                // 'verify_peer' => false,
                // 'verify_peer_name' => false,
            ],
            'http' => [
                // 'proxy' => 'tcp://127.0.0.1:8080',
                // 'request_fulluri' => true,
                'method' => 'POST',
                'follow_location' => false,
                'header' => $headerBuffer,
                'content' => $query
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
        $http = $this->getUrl($this->baseUrl . self::URL_LOGIN);
        $dom = new simple_html_dom();
        $dom->load($http['content']);
        // cerca <a> dentro il <div class="logininfo">
        $loginInfo = $dom->find('div.logininfo a', 0);
        // se lo trova recupera il contenuto di <a> e ritorna true
        if ($loginInfo) {
            print('Logged in as: ' . $loginInfo->innertext . PHP_EOL);
            return true;
        }

        // se non trova il div ritorna false
        print('Unable to login' . PHP_EOL);
        return false;
    }

    public function getCourses() {
        $myCourses = [];

        $http = $this->getUrl($this->baseUrl . self::URL_HOME);
        $dom = new simple_html_dom();
        $dom->load($http['content']);
        $courses = $dom->find('div.coursebox');
        if ($courses) {
            foreach ($courses as $course) {
                array_push($myCourses, [
                    'courseId' => $course->{'data-courseid'},
                    'courseName' => $course->find('a', 0)->innertext,
                    'courseLink' => $course->find('a', 0)->href,
                ]);
            }
        }

        return $myCourses;
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
                        array_push($courseAttendees, [
                            'attendeeName' => $row->find('th a', 0)->plaintext,
                            'attendeeEmail' => $row->find('td', 1)->plaintext,
                            'attendeeUrl' => html_entity_decode($row->find('th a', 0)->href),
                            'attendeeImage' => $row->find('th a img', 0)->src,
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

        $attendeeDetails = [
            'attendeeName' => $dom->find('div.page-header-headings h2', 0)->plaintext
        ];
        $attendeeNodes = $dom->find('div.card-body li.contentnode');
        if ($attendeeNodes) {
            foreach ($attendeeNodes as $attendeeNode) {
                $key = strtolower($attendeeNode->find('dt', 0)->plaintext);
                $value = html_entity_decode($attendeeNode->find('dd', 0)->plaintext);
                $attendeeDetails[$key] = $value;
            }
        }

        return $attendeeDetails;
    }
}
