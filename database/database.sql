DROP DATABASE IF EXISTS gymdb;

CREATE DATABASE IF NOT EXISTS gymdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymdb;

CREATE TABLE USERS (
    userID INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    userName VARCHAR(100) NOT NULL,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    birthDate DATE,
    gender ENUM('M', 'F', 'Other'),
    phoneNumber VARCHAR(20),
    role ENUM('customer', 'trainer', 'admin') DEFAULT 'customer'
);

CREATE TABLE EQUIPMENTS (
    equipmentID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('available', 'maintenance', 'broken') DEFAULT 'available',
    administratorID INT,
    FOREIGN KEY (administratorID) REFERENCES USERS(userID) ON DELETE SET NULL
);

CREATE TABLE COURSES (
    courseID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    maxParticipants INT DEFAULT 20 CHECK (maxParticipants > 0),
    currentParticipants INT DEFAULT 0 CHECK (currentParticipants >= 0 AND currentParticipants <= maxParticipants),
    startDate DATE,
    finishDate DATE
);

CREATE TABLE EXERCISES (
    exerciseID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trainerID INT,
    FOREIGN KEY (trainerID) REFERENCES USERS(userID) ON DELETE CASCADE
);

CREATE TABLE MAINTENANCES (
    maintenanceID INT PRIMARY KEY AUTO_INCREMENT,
    equipmentID INT NOT NULL,
    maintenanceDate DATE NOT NULL,
    maintenanceCost DECIMAL(10,2),
    description TEXT,
    status ENUM('scheduled', 'in_progress', 'completed') DEFAULT 'scheduled',
    FOREIGN KEY (equipmentID) REFERENCES EQUIPMENTS(equipmentID) ON DELETE CASCADE
);

CREATE TABLE AVAILABILITY_DAYS (
    availabilityDayID INT PRIMARY KEY AUTO_INCREMENT,
    trainerID INT NOT NULL,
    dayOfWeek ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    startTime TIME NOT NULL,
    finishTime TIME NOT NULL,
    FOREIGN KEY (trainerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    UNIQUE KEY unique_trainer_day_time (trainerID, dayOfWeek, startTime)
);

CREATE TABLE TRAINING_SCHEDULES (
    trainingScheduleID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    creationDate DATE DEFAULT (CURRENT_DATE),
    customerID INT NOT NULL,
    trainerID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    FOREIGN KEY (trainerID) REFERENCES USERS(userID) ON DELETE CASCADE
);

CREATE TABLE TRAINING_DAYS (
    trainingDayID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trainingScheduleID INT NOT NULL,
    dayOrder INT NOT NULL,
    FOREIGN KEY (trainingScheduleID) REFERENCES TRAINING_SCHEDULES(trainingScheduleID) ON DELETE CASCADE
);

CREATE TABLE EXERCISE_DETAILS (
    exerciseDetailID INT PRIMARY KEY AUTO_INCREMENT,
    sets INT NOT NULL,
    reps INT NOT NULL,
    weight INT,
    restTime INT,
    trainingDayID INT NOT NULL,
    exerciseID INT NOT NULL,
    orderInWorkout INT NOT NULL,
    FOREIGN KEY (trainingDayID) REFERENCES TRAINING_DAYS(trainingDayID) ON DELETE CASCADE,
    FOREIGN KEY (exerciseID) REFERENCES EXERCISES(exerciseID) ON DELETE CASCADE
);

CREATE TABLE MEMBERSHIPS (
    membershipID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration INT NOT NULL,
    description TEXT
);

CREATE TABLE PROMOTIONS (
    promotionID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    discountRate DECIMAL(5,2) NOT NULL,
    startDate DATE NOT NULL,
    expirationDate DATE NOT NULL
);

CREATE TABLE SUBSCRIPTIONS (
    subscriptionID INT PRIMARY KEY AUTO_INCREMENT,
    startDate DATE NOT NULL,
    expirationDate DATE NOT NULL,
    customerID INT NOT NULL,
    promotionID INT,
    membershipID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    FOREIGN KEY (promotionID) REFERENCES PROMOTIONS(promotionID) ON DELETE SET NULL,
    FOREIGN KEY (membershipID) REFERENCES MEMBERSHIPS(membershipID) ON DELETE CASCADE
);

CREATE TABLE PAYMENTS (
    paymentID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    customerID INT NOT NULL,
    subscriptionID INT,
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    FOREIGN KEY (subscriptionID) REFERENCES SUBSCRIPTIONS(subscriptionID) ON DELETE SET NULL
);

CREATE TABLE FEEDBACKS (
    feedbackID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    customerID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE
);

CREATE TABLE PROGRESS_REPORTS (
    progressReportID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    description TEXT,
    weight DECIMAL(5,2),
    bodyFatPercent DECIMAL(5,2),
    muscleMass DECIMAL(5,2),
    bmi DECIMAL(5,2),
    customerID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE
);

CREATE TABLE TEACHINGS (
    trainerID INT NOT NULL,
    courseID INT NOT NULL,
    PRIMARY KEY (trainerID, courseID),
    FOREIGN KEY (trainerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    FOREIGN KEY (courseID) REFERENCES COURSES(courseID) ON DELETE CASCADE
);

CREATE TABLE ENROLLMENTS (
    customerID INT NOT NULL,
    courseID INT NOT NULL,
    enrollmentDate DATE DEFAULT (CURRENT_DATE),
    PRIMARY KEY (customerID, courseID),
    FOREIGN KEY (customerID) REFERENCES USERS(userID) ON DELETE CASCADE,
    FOREIGN KEY (courseID) REFERENCES COURSES(courseID) ON DELETE CASCADE
);

CREATE INDEX idx_users_email ON USERS(email);
CREATE INDEX idx_users_role ON USERS(role);
CREATE INDEX idx_subscriptions_customer_dates ON SUBSCRIPTIONS(customerID, startDate, expirationDate);
CREATE INDEX idx_payments_customer_date ON PAYMENTS(customerID, date);
CREATE INDEX idx_teachings_trainer ON TEACHINGS(trainerID);
CREATE INDEX idx_training_schedules_trainer ON TRAINING_SCHEDULES(trainerID);
CREATE INDEX idx_maintenances_equipment_date ON MAINTENANCES(equipmentID, maintenanceDate);