DROP DATABASE IF EXISTS gymdb;

-- Creazione del database
CREATE DATABASE IF NOT EXISTS gymdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymdb;

-- Tabella USER
CREATE TABLE USER (
    userID INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    userName VARCHAR(100) NOT NULL,
    firstName VARCHAR(100) NOT NULL,
    lastName VARCHAR(100) NOT NULL,
    birthDate DATE,
    gender ENUM('M', 'F', 'Other'),
    phoneNumber VARCHAR(20),
    role ENUM('customer', 'trainer', 'admin') DEFAULT 'customer',
    specialization VARCHAR(255),
    availability TEXT,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE EQUIPMENT (
    equipmentID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    state ENUM('available', 'maintenance', 'broken') DEFAULT 'available',
    administratorID INT,
    FOREIGN KEY (administratorID) REFERENCES USER(userID) ON DELETE SET NULL
);

CREATE TABLE COURSE (
    courseID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    maxParticipants INT DEFAULT 20,
    startDate DATE,
    finishDate DATE
);

CREATE TABLE EXERCISE (
    exerciseID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT
);

CREATE TABLE MAINTENANCE (
    maintenanceID INT PRIMARY KEY AUTO_INCREMENT,
    equipmentID INT NOT NULL,
    maintenanceDate DATE NOT NULL,
    maintenanceCost DECIMAL(10,2),
    description TEXT,
    status ENUM('scheduled', 'in_progress', 'completed') DEFAULT 'scheduled',
    FOREIGN KEY (equipmentID) REFERENCES EQUIPMENT(equipmentID) ON DELETE CASCADE
);

CREATE TABLE AVAILABILITY_DAY (
    availabilityDayID INT PRIMARY KEY AUTO_INCREMENT,
    trainerID INT NOT NULL,
    dayOfWeek ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    startTime TIME NOT NULL,
    finishTime TIME NOT NULL,
    FOREIGN KEY (trainerID) REFERENCES USER(userID) ON DELETE CASCADE,
    UNIQUE KEY unique_trainer_day_time (trainerID, dayOfWeek, startTime)
);

CREATE TABLE TRAINING_SCHEDULE (
    trainingScheduleID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    creationDate DATE DEFAULT (CURRENT_DATE),
    customerID INT NOT NULL,
    trainerID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (trainerID) REFERENCES USER(userID) ON DELETE CASCADE
);

CREATE TABLE TRAINING_DAY (
    trainingDayID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    trainingScheduleID INT NOT NULL,
    dayOrder INT NOT NULL,
    FOREIGN KEY (trainingScheduleID) REFERENCES TRAINING_SCHEDULE(trainingScheduleID) ON DELETE CASCADE
);

CREATE TABLE EXERCISE_DETAIL (
    exerciseDetailID INT PRIMARY KEY AUTO_INCREMENT,
    sets INT NOT NULL,
    reps INT NOT NULL,
    weight INT,
    restTime INT,
    trainingDayID INT NOT NULL,
    exerciseID INT NOT NULL,
    orderInWorkout INT NOT NULL,
    FOREIGN KEY (trainingDayID) REFERENCES TRAINING_DAY(trainingDayID) ON DELETE CASCADE,
    FOREIGN KEY (exerciseID) REFERENCES EXERCISE(exerciseID) ON DELETE CASCADE
);

CREATE TABLE MEMBERSHIP (
    membershipID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration INT NOT NULL,
    description TEXT
);

CREATE TABLE PROMOTION (
    promotionID INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    discountRate DECIMAL(5,2) NOT NULL,
    startDate DATE NOT NULL,
    expirationDate DATE NOT NULL
);

CREATE TABLE SUBSCRIPTION (
    subscriptionID INT PRIMARY KEY AUTO_INCREMENT,
    startDate DATE NOT NULL,
    expirationDate DATE NOT NULL,
    customerID INT NOT NULL,
    promotionID INT,
    membershipID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (promotionID) REFERENCES PROMOTION(promotionID) ON DELETE SET NULL,
    FOREIGN KEY (membershipID) REFERENCES MEMBERSHIP(membershipID) ON DELETE CASCADE
);

CREATE TABLE PAYMENT (
    paymentID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('cash', 'card', 'bank_transfer', 'online') NOT NULL,
    customerID INT NOT NULL,
    subscriptionID INT,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (subscriptionID) REFERENCES SUBSCRIPTION(subscriptionID) ON DELETE SET NULL
);

CREATE TABLE FEEDBACK (
    feedbackID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    customerID INT NOT NULL,
    trainerID INT,
    courseID INT,
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (trainerID) REFERENCES USER(userID) ON DELETE SET NULL,
    FOREIGN KEY (courseID) REFERENCES COURSE(courseID) ON DELETE SET NULL
);

CREATE TABLE PROGRESS_REPORT (
    progressReportID INT PRIMARY KEY AUTO_INCREMENT,
    date DATE NOT NULL,
    description TEXT,
    weight DECIMAL(5,2),
    bodyFatPercent DECIMAL(5,2),
    muscleMass DECIMAL(5,2),
    bmi DECIMAL(5,2),
    customerID INT NOT NULL,
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE
);

-- Tabelle di relazione many-to-many

CREATE TABLE teaching (
    trainerID INT NOT NULL,
    courseID INT NOT NULL,
    PRIMARY KEY (trainerID, courseID),
    FOREIGN KEY (trainerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (courseID) REFERENCES COURSE(courseID) ON DELETE CASCADE
);

CREATE TABLE enrollment (
    customerID INT NOT NULL,
    courseID INT NOT NULL,
    enrollmentDate DATE DEFAULT (CURRENT_DATE),
    PRIMARY KEY (customerID, courseID),
    FOREIGN KEY (customerID) REFERENCES USER(userID) ON DELETE CASCADE,
    FOREIGN KEY (courseID) REFERENCES COURSE(courseID) ON DELETE CASCADE
);

CREATE TABLE servicing (
    maintenanceID INT NOT NULL,
    equipmentID INT NOT NULL,
    PRIMARY KEY (maintenanceID, equipmentID),
    FOREIGN KEY (maintenanceID) REFERENCES MAINTENANCE(maintenanceID) ON DELETE CASCADE,
    FOREIGN KEY (equipmentID) REFERENCES EQUIPMENT(equipmentID) ON DELETE CASCADE
);

-- Indici per migliorare le performance
CREATE INDEX idx_user_email ON USER(email);
CREATE INDEX idx_user_role ON USER(role);
CREATE INDEX idx_equipment_state ON EQUIPMENT(state);
CREATE INDEX idx_maintenance_date ON MAINTENANCE(maintenanceDate);
CREATE INDEX idx_subscription_dates ON SUBSCRIPTION(startDate, expirationDate);
CREATE INDEX idx_payment_date ON PAYMENT(date);
CREATE INDEX idx_feedback_rating ON FEEDBACK(rating);
CREATE INDEX idx_training_schedule_customer ON TRAINING_SCHEDULE(customerID);
CREATE INDEX idx_training_schedule_trainer ON TRAINING_SCHEDULE(trainerID);