<?php

require_once('config.php');
require_once('moodle.php');

$moodle = new Moodle(
    $config['moodle']['url'],
    $config['moodle']['username'],
    $config['moodle']['password'],
);

// Login
$moodle->login();

// Corsi disponibili
$courses = $moodle->getCourses();
print_r($courses);

// Partecipanti
$attendees = $moodle->getAttendees(4);