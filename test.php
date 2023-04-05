<?php

require_once('config.php');
require_once('moodle.php');

$moodle = new Moodle(
    $config['moodle']['url'],
    $config['moodle']['username'],
    $config['moodle']['password']
);

// Login
$moodle->login();

// Corsi disponibili
$courses = $moodle->getCourses();
print_r($courses);

// Partecipanti
$courseId = 4;
$attendees = $moodle->getAttendees($courseId);
print_r($attendees);

// Dettagli Partecipante
$attendeeId = 8;
$courseId = 4;
$attendee = $moodle->getAttendee($attendeeId, $courseId);
print_r($attendee);
