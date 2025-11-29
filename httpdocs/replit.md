# Overview

Student Dark Notebook is a personal student diary application with a unique dark pencil-sketch design aesthetic. The application supports three user roles (student, manager/group leader, and admin) and provides features for managing grades, schedules, assignments, student rankings, and debt tracking. Built with PHP 8.2 and SQLite, it's optimized for Replit deployment.

**Status**: ✅ Production-ready - Fully functional on Replit with SQLite database

**Recent Changes** (October 31, 2025):
- Migrated from MySQL to SQLite for Replit compatibility
- Fixed all form submission issues (added CSRF protection and hidden fields in debts.php)
- **Security Enhancement**: Created router.php to block HTTP access to database files and sensitive resources
- Moved database to private/ directory and configured router-based protection (returns 403 for unauthorized access)
- Cleaned up debug messages from csrf.php
- Reorganized project structure (moved files from nested directory to root)
- Updated SQL queries for SQLite compatibility (CURDATE() → DATE('now'))
- Created init_db.php script for easy database initialization
- Configured PHP 8.2 web server on port 5000 with custom router

**Admin Login**: Nurs / 9506

# User Preferences

Preferred communication style: Simple, everyday language.

# System Architecture

## Frontend Architecture

**Design System**: Dark pencil-sketch theme with handwritten aesthetic
- **Color Scheme**: Dark theme (`#1a1a1a` background) with paper-like texture overlays using CSS gradients
- **Typography**: Dual font strategy - handwritten fonts (Rock Salt, Permanent Marker) for headers and titles, readable sans-serif fonts (Inter, Roboto) for body text
- **Visual Style**: Hand-drawn appearance achieved through CSS borders and shadows to simulate pencil sketches
- **Responsive Design**: Mobile-first approach with hamburger menu (☰) navigation, sidebar that slides in on mobile devices
- **Component Structure**: Card-based layout for displaying schedules, grades, and assignments

**Rationale**: The unique pencil-sketch design creates a distinctive user experience that mimics a physical notebook, making the digital platform feel more personal and engaging for students.

## Backend Architecture

**Technology Stack**: PHP 8.2 with SQLite 3
- **Architecture Pattern**: Traditional server-side rendering with PHP
- **Database Layer**: SQLite file-based database (private/database.sqlite) via PDO
- **Session Management**: PHP sessions for user authentication and role management
- **File Structure**: Page-based routing (index.php, grades.php, tasks.php, debts.php, etc.)
- **Security Router**: Custom router.php blocks direct HTTP access to sensitive files (.sqlite, private/, init_db.php)

**Rationale**: PHP with SQLite provides a lightweight, zero-configuration solution perfect for Replit deployment. No separate database server needed, and all data is stored in a single file that persists across deployments. The custom router ensures database files cannot be downloaded via HTTP, critical for PHP's built-in server which doesn't support .htaccess.

## Authentication & Authorization

**Role-Based Access Control**: Three-tier permission system
- **Student Role**: View personal schedule, grades, assignments, and rankings
- **Manager Role**: Group leader capabilities (likely includes managing group members and assignments)
- **Admin Role**: Full system access for managing all users, schedules, and data

**Security Approach**: Session-based authentication with server-side role validation

**Rationale**: Simple role hierarchy matches the academic structure (students, group leaders, administrators) while keeping implementation straightforward for a single-school deployment.

## Data Architecture

**Database Design**: Relational schema initialized through `init_db.php`
- **Core Entities**: Users, schedules, grades, assignments, subjects, teachers, debts
- **Schema Deployment**: Automated via PHP script (init_db.php) - run once to create tables and seed data
- **Connection Management**: Centralized SQLite configuration in `db.php`
- **Database File**: private/database.sqlite (auto-created in protected directory, excluded from git)
- **HTTP Protection**: router.php blocks all direct access to /private/ directory and .sqlite files

**Key Features Supported**:
- Grade calculation system with automated scoring: (RK1 + RK2) × 0.6 + Exam% × 0.4
- Daily schedule management with time slots, classrooms, and teachers
- Assignment tracking with completion status and overdue detection
- Student ranking system based on total points
- Debt/payment tracking with due dates

**Rationale**: SQLite provides reliable relational data storage in a single file, perfect for Replit's ephemeral filesystem with persistent storage. No separate database server required, and the database file is automatically backed up with the project.

## User Interface Components

**Navigation System**: Mobile-responsive sidebar menu
- **Desktop**: Persistent navigation
- **Mobile**: Hamburger menu with overlay, body scroll lock when open
- **Close Actions**: Dedicated close button and overlay click-to-close

**Interactive Features**:
- Date navigation with arrow controls for viewing different days' schedules
- Auto-dismissing success/error messages (5-second timeout with fade animation)
- Form validation for required fields
- Smooth CSS transitions for all interactive elements

**Rationale**: Progressive enhancement approach ensures core functionality works everywhere while providing enhanced experience on capable devices.

# External Dependencies

## Required Server Environment

**Web Server**: PHP built-in development server (for development/Replit)
- Production: Apache or Nginx with PHP-FPM
- Replit: Configured on port 5000 with 0.0.0.0 binding

**PHP Runtime**: Version 8.2
- Server-side scripting for application logic
- Session management capabilities required
- PDO SQLite extension enabled

**Database**: SQLite 3
- File-based database (database.sqlite)
- No separate database server required
- Automatic schema creation via init_db.php

## Frontend Libraries

**Fonts**: Google Fonts or similar CDN
- Handwritten fonts: "Rock Salt", "Permanent Marker" (for headers/titles)
- Body fonts: "Inter", "Roboto" (for readable content)

**JavaScript**: Vanilla JavaScript (no frameworks)
- Native DOM manipulation for interactivity
- No external JS libraries required

**CSS**: Pure CSS3
- No preprocessors or frameworks
- Custom design system with CSS variables
- Grid-based textures using linear gradients

## Deployment Configuration

**Replit Setup** (Current):
1. PHP 8.2 module installed automatically
2. Database initialized via `php init_db.php` (already done)
3. Web server configured with router: `php -S 0.0.0.0:5000 router.php`
4. Security router blocks access to sensitive files (database, config, init script)
5. No additional configuration needed

**Manual Deployment** (Other Hosts):
1. Upload all files to web root
2. Ensure PHP 8.2+ with SQLite extension
3. Run `php init_db.php` to create database
4. Configure web server to serve from project root
5. Ensure database.sqlite is writable by web server

**Configuration** (db.php):
- Database path: `__DIR__ . '/private/database.sqlite'`
- No credentials needed (file-based database)
- Foreign keys enabled via PRAGMA
- HTTP access blocked via router.php (returns 403 for /private/* requests)

**Rationale**: Zero-dependency approach (beyond PHP/SQLite) minimizes hosting requirements and ensures compatibility with any PHP hosting environment. SQLite eliminates database server configuration complexity.