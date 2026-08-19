# VitalCore
> ## 🚧 Development Notice
> **VitalCore is currently under active development.**
> This project is **not yet fully completed**, and some features, hardware integrations, designs, and system functionalities are still being developed, tested, and improved.
> The information and features presented in this repository may change as development continues. Some features may not yet be fully functional or may be modified in future updates.
> This repository represents the **current development version of VitalCore** and is intended to document the progress of the project.

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

**Jhoshua Concepcion Laurito**

Computer Engineering Student
Philippines

### GitHub

https://github.com/JhosDev4

---

> **Note:** VitalCore is an educational and development project. It is not intended to replace professional medical diagnosis, clinical judgment, or certified medical equipment.
