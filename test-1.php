<?php

require_once('config.php');

// Moodle template parser
require_once('moodle-1.php');

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
$courseId = 969;
$attendees = $moodle->getAttendees($courseId);
print_r($attendees);

// Dettagli Partecipante
$attendeeId = 40802;
$courseId = 969;
$attendee = $moodle->getAttendeeDetail($attendeeId, $courseId);
print_r($attendee);
