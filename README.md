# Moodle Client

Moodle HTTP Client Interface

## PoC

### Login

```
$moodle->login();
```

```
Logged in as: Mario Rossi
```

### Get Courses
```
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