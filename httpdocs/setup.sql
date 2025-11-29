-- Student Dark Notebook Database Setup
-- Run this file to create the database structure

CREATE DATABASE IF NOT EXISTS student_notebook CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE student_notebook;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) DEFAULT '',
  avatar VARCHAR(255) DEFAULT '',
  role ENUM('student', 'manager', 'admin') DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subjects table
CREATE TABLE IF NOT EXISTS subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  teacher VARCHAR(100) DEFAULT '',
  teacher_phone VARCHAR(20) DEFAULT '',
  max_points INT DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grades table
CREATE TABLE IF NOT EXISTS grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject_id INT NOT NULL,
  rk1 FLOAT DEFAULT 0,
  rk2 FLOAT DEFAULT 0,
  exam_score FLOAT DEFAULT 0,
  exam_max FLOAT DEFAULT 100,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY user_subject (user_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks table
CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  due_date DATE NOT NULL,
  points FLOAT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Task completions
CREATE TABLE IF NOT EXISTS task_completions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  task_id INT NOT NULL,
  is_done BOOLEAN DEFAULT FALSE,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  UNIQUE KEY user_task (user_id, task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Schedule table
CREATE TABLE IF NOT EXISTS schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  subject_id INT NOT NULL,
  time VARCHAR(20) NOT NULL,
  room VARCHAR(20) DEFAULT '',
  teacher VARCHAR(100) DEFAULT '',
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  INDEX date_idx (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Debts table
CREATE TABLE IF NOT EXISTS debts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject_id INT NOT NULL,
  description TEXT NOT NULL,
  due_date DATE NOT NULL,
  room VARCHAR(50) DEFAULT '',
  is_completed BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert super user (login: nnnursultan, password: 72416810)
INSERT INTO users (username, password, full_name, role) VALUES
('nnnursultan', '$2y$10$n.qZ6OPD13zSBe5JGHRyBuTRXw6qLhWHVZneygi4oVtl7eJCzrXZ2', 'Супер пользователь', 'admin')
ON DUPLICATE KEY UPDATE username=username;

-- Insert sample subjects
INSERT INTO subjects (name, teacher, max_points) VALUES
('Математика', 'Иванов И.И.', 100),
('Программирование', 'Петров П.П.', 100),
('Физика', 'Сидоров С.С.', 100),
('Английский язык', 'Смирнова А.А.', 100)
ON DUPLICATE KEY UPDATE name=name;