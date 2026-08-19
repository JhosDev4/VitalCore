# VitalCore

**VitalCore** is a healthcare monitoring and patient management system designed to assist healthcare personnel in recording patient information, measuring vital signs, and managing health service records.

The system combines a web-based management dashboard with a touchscreen kiosk and hardware sensors to provide a more organized and efficient patient monitoring workflow.

## Features

### 🏥 Patient Management

* Patient registration
* Patient information management
* Patient history
* Patient records
* Patient search and filtering
* Patient record viewing

### 🩺 Vital Sign Monitoring

VitalCore supports the recording of:

* Height
* Weight
* Body Mass Index (BMI)
* Body Temperature
* Heart Rate
* Blood Pressure
* Blood Oxygen Saturation (SpO₂)

### 📋 Healthcare Services

The kiosk allows patients to select their service:

* Vital Screening
* Prenatal Check-up
* Child Immunization
* Family Planning

### 🖥️ Healthcare Kiosk

The touchscreen kiosk provides a guided patient workflow:

1. Patient enters their generated code.
2. Patient selects a healthcare service.
3. The kiosk provides measurement instructions.
4. Available sensors collect vital measurements.
5. The results are displayed to the patient.
6. The records can be reviewed by authorized healthcare personnel.

### 📊 Admin Dashboard

The administrative dashboard provides:

* Patient statistics
* Patient records
* Service records
* Check-up history
* Reports
* Measurement records
* Patient document generation

## Hardware

VitalCore is designed to work with a Raspberry Pi-based kiosk and health monitoring sensors.

Current hardware includes:

* Raspberry Pi 4
* Touchscreen display
* MLX90614 temperature sensor
* TF-Luna distance sensor
* HX711 load cell
* MAX30102 sensor
* Blood pressure monitor

## Technologies

### Frontend

* HTML5
* CSS3
* JavaScript
* Bootstrap
* Bootstrap Icons

### Backend

* PHP
* MySQL
* Apache

### Hardware / IoT

* Raspberry Pi
* Python
* I2C sensors
* Health monitoring sensors

### Development Tools

* XAMPP
* Git
* GitHub

## System Architecture



## Installation

### Requirements

Before running VitalCore, install:

* XAMPP
* PHP
* MySQL
* Git
* A modern web browser

For the hardware kiosk, a Raspberry Pi and the required sensors are also needed.

### 1. Clone the Repository

```bash
git clone https://github.com/JhosDev4/VitalCore.git
```

### 2. Move the Project

Place the project inside the XAMPP `htdocs` directory:

```text
C:\xampp\htdocs\Vitalcore
```

### 3. Create the Database

Open:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
vitalcore_db
```

Import the database structure from:

```text
req/data.sql
```

### 4. Configure the Database

Create your local `db_conn.php` file and configure the MySQL connection for your environment.

Example:

```php
<?php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "vitalcore_db";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}
?>
```

### 5. Start XAMPP

Start:

```text
Apache
MySQL
```

Then open:

```text
http://localhost/Vitalcore/
```

## Project Structure

```text
VitalCore/
│
├── admin/
│   ├── Doc/
│   ├── Measure/
│   ├── Service/
│   ├── logs/
│   ├── admin-dashboard.php
│   ├── dashboard.php
│   ├── patient-list.php
│   └── patient-view.php
│
├── assets/
│   ├── guide/
│   └── js/
│
├── css/
│
├── img/
│
├── kiosk/
│   ├── index.php
│   ├── measure-result.php
│   ├── measure_all.php
│   └── sensor endpoints
│
├── req/
│   ├── data.sql
│   └── login.php
│
├── login.php
│
└── .gitignore
```

## Project Goals

VitalCore was developed to explore how web applications, databases, touchscreen interfaces, and hardware sensors can work together to improve healthcare data collection and patient monitoring.

The project focuses on:

* Digital patient records
* Automated vital sign collection
* Healthcare service organization
* Raspberry Pi integration
* Touchscreen kiosk interaction
* Centralized health records
* 
## Developer

**Jhoshua Laurito**

Computer Engineering Student
Philippines

### GitHub

https://github.com/JhosDev4

---

> **Note:** VitalCore is an educational and development project. It is not intended to replace professional medical diagnosis, clinical judgment, or certified medical equipment.
