USE gymdb;

-- Inserimento utenti (già presenti nel tuo file originale, li ripeto solo per contesto)
INSERT INTO USERS (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role) VALUES
('admin@gym.com', '$2y$10$9v2OOlFuBDzlqaRGBbMKfONJrpPOfk4iiu83tnQB1gdsU1ZdZUY6.', 'adminUser', 'Alice', 'Admin', '1980-01-01', 'F', '1234567890', 'admin'),
('trainer1@gym.com', '$2y$10$lMEuQiVE1R5/dQfM9gw/E.QJ/gLdXaqN26.ZjmimfHZ1alOBR0/ym', 'fitTrainer', 'Bob', 'Trainer', '1990-05-15', 'M', '0987654321', 'trainer'),
('customer1@gym.com', '$2y$10$c1xd0JeTBuF9dXoGuwOq5uKo4aVaDOEpn/C2AqMA0P9FkPc9LjOEm', 'john_doe', 'John', 'Doe', '1995-03-20', 'M', '1112223333', 'customer'),
('customer2@gym.com', '$2y$10$BASb5fmC42Wfp732AXS3ueRdLVKPhUqb8C6kDisf5.lewXirEhSqC', 'jane_doe', 'Jane', 'Doe', '1992-07-10', 'F', '4445556666', 'customer'),
('customer3@gym.com', '$2y$10$Z0wrccZSukbEpYJ7/jFj4uhc55hEZ.ETvrR20.fsJo4EPuktKXOCG', 'mike_smith', 'Mike', 'Smith', '1988-11-05', 'M', '7778889999', 'customer'),
('customer4@gym.com', '$2y$10$gNt8TTTBT8zb55J8JHCjyOP1bdEw2H92n1PXfYQAs/b6ujLTBAAPu', 'emma_jones', 'Emma', 'Jones', '1990-02-25', 'F', '2223334444', 'customer');

-- EQUIPMENTS
INSERT INTO EQUIPMENTS (name, description, state, administratorID) VALUES
('Tapis roulant', 'Tapis roulant elettrico con varie velocità', 'available', 1),
('Cyclette', 'Bicicletta statica per esercizi cardio', 'maintenance', 1),
('Panca Piana', 'Panca per sollevamento pesi', 'available', 1),
('Manubri Set', 'Set completo di manubri fino a 40kg', 'broken', 1);

-- COURSES
INSERT INTO COURSES (name, description, maxParticipants, startDate, finishDate) VALUES
('Yoga Base', 'Corso base di Yoga per principianti', 15, '2025-07-01', '2025-09-30'),
('Pilates Avanzato', 'Corso avanzato di Pilates', 10, '2025-07-15', '2025-10-15'),
('Functional Training', 'Allenamento funzionale per tutti i livelli', 12, '2025-08-01', '2025-10-31'),
('HIIT Morning', 'Allenamento ad alta intensità al mattino', 8, '2025-07-20', '2025-09-30');

-- EXERCISES
INSERT INTO EXERCISES (name, description, trainerID) VALUES
('Squat', 'Esercizio per gambe e glutei', 2),
('Push-up', 'Esercizio per petto e braccia', 2),
('Deadlift', 'Sollevamento da terra', 2),
('Plank', 'Esercizio di tenuta addominale', 2);

-- MAINTENANCES
INSERT INTO MAINTENANCES (equipmentID, maintenanceDate, maintenanceCost, description, status) VALUES
(2, '2025-06-25', 100.00, 'Sostituzione cinghia', 'scheduled'),
(4, '2025-07-05', 50.00, 'Riparazione impugnature', 'in_progress');

-- AVAILABILITY_DAYS
INSERT INTO AVAILABILITY_DAYS (trainerID, dayOfWeek, startTime, finishTime) VALUES
(2, 'Monday', '09:00:00', '17:00:00'),
(2, 'Wednesday', '09:00:00', '17:00:00'),
(2, 'Friday', '14:00:00', '18:00:00');

-- TRAINING_SCHEDULES
INSERT INTO TRAINING_SCHEDULES (name, description, creationDate, customerID, trainerID) VALUES
('Piano base John', 'Programma di allenamento per principianti', CURDATE(), 3, 2),
('Piano avanzato Jane', 'Programma intensivo avanzato', CURDATE(), 4, 2);

-- TRAINING_DAYS
INSERT INTO TRAINING_DAYS (name, description, trainingScheduleID, dayOrder) VALUES
('Giorno 1', 'Focus gambe e core', 1, 1),
('Giorno 2', 'Upper body', 1, 2),
('Giorno 1', 'Full Body', 2, 1);

-- EXERCISE_DETAILS
INSERT INTO EXERCISE_DETAILS (sets, reps, weight, restTime, trainingDayID, exerciseID, orderInWorkout) VALUES
(3, 12, 50, 60, 1, 1, 1),
(4, 15, NULL, 45, 2, 2, 1),
(4, 8, 80, 90, 3, 3, 1),
(3, 1, NULL, 120, 3, 4, 2);

-- MEMBERSHIPS
INSERT INTO MEMBERSHIPS (name, price, duration, description) VALUES
('Abbonamento Mensile', 30.00, 30, 'Accesso illimitato per un mese'),
('Abbonamento Annuale', 300.00, 365, 'Accesso illimitato per un anno'),
('Abbonamento Trimestrale', 80.00, 90, 'Accesso illimitato per tre mesi');

-- PROMOTIONS
INSERT INTO PROMOTIONS (name, description, discountRate, startDate, expirationDate) VALUES
('Promo Estate', 'Sconto estivo del 20%', 20.00, '2025-06-01', '2025-08-31'),
('Back to Gym', 'Sconto autunnale del 15%', 15.00, '2025-09-01', '2025-11-30');

-- SUBSCRIPTIONS
INSERT INTO SUBSCRIPTIONS (startDate, expirationDate, customerID, promotionID, membershipID) VALUES
('2025-06-01', '2025-06-30', 3, 1, 1),
('2025-07-01', '2025-07-03', 3, NULL, 2),
('2025-06-10', '2025-09-08', 4, 1, 3),
('2025-07-01', '2026-06-30', 5, NULL, 2),
('2025-07-01', '2025-09-30', 6, 2, 3),
('2025-07-16', '2026-08-16', 3, NULL, 2);

-- PAYMENTS
INSERT INTO PAYMENTS (date, amount, customerID, subscriptionID) VALUES
('2025-06-01', 69.00, 3, 1),
('2025-07-01', 300.00, 3, 2),
('2025-06-10', 80.00, 4, 3),
('2025-07-01', 300.00, 5, 4),
('2025-07-01', 80.00, 6, 5),
('2025-07-01', 300.00, 3, 6);

-- FEEDBACKS
INSERT INTO FEEDBACKS (date, rating, comment, customerID) VALUES
('2025-06-21', 5, 'Ottima palestra!', 4),
('2025-06-22', 4, 'Personale gentile.', 5),
('2025-07-01', 3, 'Potrebbero migliorare gli spogliatoi.', 6);

-- PROGRESS_REPORTS
INSERT INTO PROGRESS_REPORTS (date, description, weight, bodyFatPercent, muscleMass, bmi, customerID) VALUES
('2025-06-15', 'Prima valutazione', 80.00, 18.5, 30.0, 24.0, 3),
('2025-06-18', 'Valutazione mensile', 68.00, 22.0, 28.0, 21.5, 4),
('2025-07-05', 'Controllo progressi estate', 85.00, 17.0, 32.0, 25.5, 5);

-- TEACHING
INSERT INTO TEACHING (trainerID, courseID) VALUES
(2, 1),
(2, 2),
(2, 3),
(2, 4);

-- ENROLLMENT
INSERT INTO ENROLLMENT (customerID, courseID) VALUES
(3, 1),
(4, 2),
(5, 3),
(6, 1),
(6, 4);