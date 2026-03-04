# SkillORA — Modular E-Learning Platform

## Overview
This project was developed as part of the **PIDEV — 3rd Year Engineering Program** at **Esprit School of Engineering** (Academic Year **2025-2026**).

SkillORA is a modular e-learning platform designed to provide structured digital learning experiences. The platform allows instructors to create and manage courses, students to learn and be evaluated, and the community to interact through discussions, events, and instructor appointments.

---

## Features
- Course creation and management
- Structured lessons (videos, PDFs, learning resources)
- Course completion tracking and certificate generation
- Student evaluations and grading
- Forum discussions and community interaction
- Events and hackathons management
- Instructor rendez-vous system (students can schedule meetings with instructors)
- Role-based access control (Admin / Instructor / Student)

---

## Tech Stack

### Backend
- Symfony (PHP)
- Doctrine ORM
- MySQL
- Symfony Security (RBAC)

### Frontend
- Twig
- Bootstrap / Tailwind CSS
- JavaScript

---

## Architecture
SkillORA follows a **layered modular architecture**:

```
Controller → Service → Repository → Entity
```

Each module is separated by domain (Users, Courses, Evaluations, Forum, Events, Rendez-Vous) to ensure scalability, maintainability, and clean system design.

---

## Contributors
- Nidhal Messaoudi
- Omar Jebali
- Ala Rezgui
- Yesser Boubakri
- Rayen Doggaz
- Sarra Souidi

---

## Academic Context
Developed at **Esprit School of Engineering — Tunisia**  
**PIDEV — 3A46**  
Academic Year **2025-2026**

---

## Getting Started

Clone the repository:

```bash
git clone https://github.com/YOUR-ORG/Esprit-PIDEV-3A46-2026-SkillORA.git
cd Esprit-PIDEV-3A46-2026-SkillORA
```

Install dependencies:

```bash
composer install
```

Configure environment variables (`.env.local`) for database connection.

Run database migrations:

```bash
php bin/console doctrine:migrations:migrate
```

Start the development server:

```bash
symfony server:start
```

Open in browser:

```
http://127.0.0.1:8000
```

---

## Acknowledgments
Project tutors:

- Meriem Mriri  
- Asma Ayari
