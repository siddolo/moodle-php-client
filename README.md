# Moodle Client

Moodle HTTP Client Interface

## PoC

### Login

```
// Login
$moodle->login();
```

```
Logged in as: Mario Rossi
```

### Get Courses

```
// Corsi disponibili
$courses = $moodle->getCourses();
print_r($courses);
```
```
Array
(
    [0] => Array
        (
            [courseId] => 6
            [courseName] => Foo
            [courseLink] => https://elearning.foo.it/course/view.php?id=6
        )

    [1] => Array
        (
            [courseId] => 5
            [courseName] => Bar
            [courseLink] => https://elearning.foo.it/course/view.php?id=5
        )
...
```

### Get Attendees

```
// Partecipanti
$courseId = 4;
$attendees = $moodle->getAttendees($courseId);
print_r($attendees);
```

```
Array
(
    [0] => Array
        (
            [attendeeName] => Utente Demo
            [attendeeEmail] => utente@demo.cor
            [attendeeUrl] => https://elearning.foo.it/user/view.php?id=3&course=4
            [attendeeImage] => https://elearning.foo.it/theme/image.php/boost/core/1675336758/u/f2
        )
...
```
