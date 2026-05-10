# IntelliLink – Integrated Job Offer Management Platform

## Overview

IntelliLink is a web application developed using **Symfony** and **PHP** designed to bridge the gap between students and the professional world. The platform facilitates managing job offers, tracking applications, and centralizing interactions between recruiters and candidates.

Developed as part of the module **PIDEV – 3rd Year Engineering Program** at Esprit School of Engineering – Tunisia (Academic Year 2025–2026).

---

## Features

* **Job Offer & Application Management**: Seamless publication and administration of career opportunities.
* **Resume Skill Analysis**: Data extraction to optimize candidate matching.
* **Application Tracking**: Real-time interface to monitor the progress of recruitment stages.
* **Candidate Scoring System**: Evaluation algorithms based on specific job requirements.
* **Community Forum**: A dedicated space for networking and exchange between users.
* **Collaborative Project Management**: Tools for teamwork on innovative academic or professional projects.
* **User Management & Authentication**: Secure login system with role-based access control.

---

## Tech Stack

* **Backend**: PHP 8+, Symfony Framework
* **Frontend**: Twig 2.0 (Template Engine), JavaScript (ES6+), HTML5, CSS3
* **Database**: MySQL
* **Tools**: Composer, Git, GitHub

---

## Architecture

The project is organized into several core Symfony modules:

* **job_management**: Logic for job listings and recruitment.
* **forum**: Module for social interaction and knowledge sharing.
* **project**: Tools for collaborative workspaces.
* **user**: Authentication and profile management using Symfony Security.

---

## Getting Started

1. Clone the GitHub repository.
2. Install dependencies via `composer install`.
3. Configure the `.env` file for your MySQL database.
4. Run database migrations: `php bin/console doctrine:migrations:migrate`.
5. Launch the local server: `symfony serve`.

---

## Contributors
**Team IntelliLink** – Esprit School of Engineering

## Academic Context
Developed at Esprit School of Engineering – Tunisia  
**PIDEV – 3A | Academic Year 2025–2026**

## Acknowledgments
Esprit School of Engineering – Tunisia
