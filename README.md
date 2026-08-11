# Smart Timetabler

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Python](https://img.shields.io/badge/Python-3776AB?style=for-the-badge&logo=python&logoColor=white)](https://www.python.org/)
[![Flask](https://img.shields.io/badge/Flask-000000?style=for-the-badge&logo=flask&logoColor=white)](https://flask.palletsprojects.com/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)](https://developer.mozilla.org/en-US/docs/Web/JavaScript)

> A distributed, full-stack scheduling and task optimisation engine built as part of the 1st Year Computer Science curriculum at **The University of Manchester**.

---

## Live Demo

*  **University Students:** [Live Application](https://smarttimetabler.infinityfreeapp.com)
*  **Recruiters & Guests:** [Instant Sandbox/Demo Dashboard](https://smarttimetabler.infinityfreeapp.com/demo_login.php) *(No registration required; pre-loaded with mock timetables and tasks)*
*  **Demo Video:** [Linkedin Post](https://lnkd.in/p/e2NMmkhw)
---

## Project Overview

University schedules change constantly, and manual task management leads to calendar fragmentation and burnout. **Smart Timetabler** automates schedule management by fetching external timetable feeds, monitoring task progress, and automatically executing continuous-block rescheduling algorithms to resolve calendar clashes without user intervention.

---

## Architecture & Key Features

```mermaid
graph TD
    A[Frontend: HTML5 / CSS3 / JS] -->|Fetch / AJAX| B[Primary Backend: PHP / MySQL]
    B -->|cURL JSON Payload| C[Microservice: Python Flask]
    C -->|JSON Response| B
    C -->|API Requests| D[External Services: Canvas / iCal / Maps]

    style A fill:#1f2937,stroke:#3b82f6,stroke-width:2px,color:#fff
    style B fill:#1f2937,stroke:#10b981,stroke-width:2px,color:#fff
    style C fill:#1f2937,stroke:#f59e0b,stroke-width:2px,color:#fff
    style D fill:#1f2937,stroke:#6366f1,stroke-width:2px,color:#fff
```



### 1. Decoupled Microservice Architecture
* **Primary Server (PHP):** Handles core HTTP routing, session security, database operations, and user authentication.
* **Algorithm Microservice (Python / Flask):** Offloads resource-heavy calendar parsing and complex array-manipulation math from the main web server to maintain fast response times.

### 2. Smart Rescheduling Algorithm
* Converts incoming task matrices and fixed lecture blocks into continuous availability maps.
* Calculates available continuous time slots during user-configured working hours, dynamically inserting overdue or pending tasks while strictly preventing calendar collisions.

### 3. Asynchronous & Background Pipeline
* **Non-blocking UI:** Utilizes JavaScript `fetch()` and asynchronous workflows (`week_view.js`) to plot calendar blocks without forcing full page reloads.
* **Background Worker (`auto_sync.php`):** Periodically polls university iCal feeds and Canvas API endpoints in the background, keeping calendar states up to date silently.

### 4. Normalized Database & Automated Sweeping
* Designed a fully normalized **MySQL schema** (3NF) featuring junction tables (`ToDo_Tags`) for dynamic, reusable hex-colored tags.
* Implemented automated database cleanup logic to permanently purge completed tasks 24 hours post-completion timestamp, optimizing storage efficiency.

##  Tech Stack

* **Languages:** PHP, Python 3, JavaScript (ES6+), SQL, HTML5, CSS3
* **Frameworks & Libraries:** Flask, cURL
* **Database:** MySQL
* **Hosting / Infrastructure:** InfinityFree (Primary Web Server & DB), Render (Python API Service)

---

##  Database Schema (ERD)

```mermaid
erDiagram
    USERS ||--o{ EVENTS : "has"
    USERS ||--o{ TODO : "has"
    USERS ||--o| USER_SETTINGS : "configures"

    TODO ||--o{ TODO_TAGS : "tagged with"
    TAGS ||--o{ TODO_TAGS : "applied to"

    USERS {
        int UserID PK
        string username
        string email
    }

    USER_SETTINGS {
        int UserID PK
        string display_name
        string email
        int start_hour
        int end_hour
        string postcode
        string preferred_transport
        datetime last_synced
    }

    EVENTS {
        int EventID PK
        int UserID FK
        string module
        datetime start_time
        int duration
        string type
        string location
    }

    TODO {
        int taskID PK
        int UserID FK
        string title
        int duration
        datetime deadline
        boolean is_complete
        datetime completed_at
    }

    TAGS {
        int TagID PK
        string tag_name
        string color
    }

    TODO_TAGS {
        int taskID FK
        int TagID FK
    }
```


Authors
Darragh Coleman – University of Manchester
Co-developed alongside team members for the Year 1 Team Project.
