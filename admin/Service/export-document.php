<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

$conn = mysqli_connect("localhost", "root", "", "vitalcore_db");

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid patient ID.");
}

$patient_id = (int) $_GET['id'];

/* Get patient's service type */
$query = mysqli_query(
    $conn,
    "SELECT service_type
     FROM users
     WHERE id = $patient_id
     AND role = 'patient'
     LIMIT 1"
);

if (!$query || mysqli_num_rows($query) === 0) {
    die("Patient not found.");
}

$patient = mysqli_fetch_assoc($query);

$service_type = trim($patient['service_type'] ?? '');

/* Select document based on service type */
switch ($service_type) {

    case 'Vital Screening':
        $document = 'docu-vital-screening.php';
        break;

    case 'Prenatal Check-up':
        $document = 'docu-prenatal.php';
        break;

    case 'Child Immunization':
        $document = 'docu-child-immunization.php';
        break;

    case 'Family Planning':
        $document = 'docu-family-planning.php';
        break;

    default:
        die(
            "No export document available for service type: " .
            htmlspecialchars($service_type)
        );
}

/*
 * export-document.php is inside:
 *
 * admin/Service/
 *
 * Documents are inside:
 *
 * admin/Doc/
 *
 * So we go one level up and enter Doc.
 */
$document_path = __DIR__ . '/../Doc/' . $document;

/* Check if document exists */
if (!is_file($document_path)) {
    die(
        "Document file not found:<br><br>" .
        "<strong>" . htmlspecialchars($document_path) . "</strong>"
    );
}

/* Pass patient ID to the document */
$_GET['id'] = $patient_id;

/* Load selected document */
require $document_path;

exit();
?>