USE gymdb;

-- Inserimento utenti
INSERT INTO USER (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role, specialization, availability) VALUES
('admin@gym.com', 'adminpass', 'adminUser', 'Alice', 'Admin', '1980-01-01', 'F', '1234567890', 'admin', NULL, NULL),
('trainer1@gym.com', 'trainerpass', 'fitTrainer', 'Bob', 'Trainer', '1990-05-15', 'M', '0987654321', 'trainer', 'Yoga, Pilates', 'Mon-Fri 9am-5pm'),
('customer1@gym.com', 'custpass', 'john_doe', 'John', 'Doe', '1995-03-20', 'M', '1112223333', 'customer', NULL, NULL),
('customer2@gym.com', 'custpass2', 'jane_doe', 'Jane', 'Doe', '1992-07-10', 'F', '4445556666', 'customer', NULL, NULL),
('customer3@gym.com', 'custpass3', 'mike_smith', 'Mike', 'Smith', '1988-11-05', 'M', '7778889999', 'customer', NULL, NULL),
('customer4@gym.com', 'custpass4', 'emma_jones', 'Emma', 'Jones', '1990-02-25', 'F', '2223334444', 'customer', NULL, NULL);

-- Inserimento attrezzature
INSERT INTO EQUIPMENT (name, description, state, administratorID) VALUES
('Tapis roulant', 'Tapis roulant elettrico con varie velocità', 'available', 1),
('Cyclette', 'Bicicletta statica per esercizi cardio', 'maintenance', 1);

-- Inserimento corsi
INSERT INTO COURSE (name, description, maxParticipants, startDate, finishDate) VALUES
('Yoga Base', 'Corso base di Yoga per principianti', 15, '2025-07-01', '2025-09-30'),
('Pilates Avanzato', 'Corso avanzato di Pilates', 10, '2025-07-15', '2025-10-15'),
('Functional Training', 'Allenamento funzionale per tutti i livelli', 12, '2025-08-01', '2025-10-31');

-- Inserimento esercizi
INSERT INTO EXERCISE (name, description, trainerID) VALUES
('Squat', 'Esercizio per gambe e glutei', 2),
('Push-up', 'Esercizio per petto e braccia', 2);

-- Inserimento manutenzioni
INSERT INTO MAINTENANCE (equipmentID, maintenanceDate, maintenanceCost, description, status) VALUES
(2, '2025-06-25', 100.00, 'Sostituzione cinghia', 'scheduled');

-- Inserimento disponibilità trainer
INSERT INTO AVAILABILITY_DAY (trainerID, dayOfWeek, startTime, finishTime) VALUES
(2, 'Monday', '09:00:00', '17:00:00'),
(2, 'Wednesday', '09:00:00', '17:00:00');

-- Inserimento piano di allenamento
INSERT INTO TRAINING_SCHEDULE (name, description, creationDate, customerID, trainerID) VALUES
('Piano base John', 'Programma di allenamento per principianti', CURDATE(), 3, 2);

-- Inserimento giorni di allenamento
INSERT INTO TRAINING_DAY (name, description, trainingScheduleID, dayOrder) VALUES
('Giorno 1', 'Focus gambe e core', 1, 1),
('Giorno 2', 'Upper body', 1, 2);

-- Inserimento dettagli esercizi in un giorno di allenamento
INSERT INTO EXERCISE_DETAIL (sets, reps, weight, restTime, trainingDayID, exerciseID, orderInWorkout) VALUES
(3, 12, 50.00, 60, 1, 1, 1),
(4, 15, NULL, 45, 2, 2, 1);

-- Inserimento abbonamenti
INSERT INTO MEMBERSHIP (name, price, duration, description) VALUES
('Abbonamento Mensile', 30.00, 30, 'Accesso illimitato per un mese'),
('Abbonamento Annuale', 300.00, 365, 'Accesso illimitato per un anno'),
('Abbonamento Trimestrale', 80.00, 90, 'Accesso illimitato per tre mesi');

-- Inserimento promozioni
INSERT INTO PROMOTION (name, description, discountRate, startDate, expirationDate) VALUES
('Promo Estate', 'Sconto estivo del 20%', 20.00, '2025-06-01', '2025-08-31');

-- Sottoscrizioni (più clienti)
INSERT INTO SUBSCRIPTION (startDate, expirationDate, customerID, promotionID, membershipID) VALUES
('2025-06-01', '2025-06-30', 3, 1, 1),
('2025-06-10', '2025-09-08', 4, 1, 3),
('2025-07-01', '2026-06-30', 5, NULL, 2),
('2025-07-01', '2025-09-30', 6, NULL, 3);

-- Pagamenti (associati alle sottoscrizioni)
INSERT INTO PAYMENT (date, amount, customerID, subscriptionID, status) VALUES
('2025-06-01', 24.00, 3, 1, 'completed'),
('2025-06-10', 64.00, 4, 2, 'completed'),
('2025-07-01', 300.00, 5, 3, 'completed'),
('2025-07-01', 80.00, 6, 4, 'completed');

-- Inserimento feedback
INSERT INTO FEEDBACK (date, rating, comment, customerID) VALUES
('2025-06-20', 1, 'NOOOOOO', 3),
('2025-06-21', 5, 'Ottima palestra!', 4),
('2025-06-22', 4, 'Personale gentile.', 5);

-- Inserimento report di progresso
INSERT INTO PROGRESS_REPORT (date, description, weight, bodyFatPercent, muscleMass, bmi, customerID) VALUES
('2025-06-15', 'Prima valutazione', 80.00, 18.5, 30.0, 24.0, 3),
('2025-06-18', 'Valutazione mensile', 68.00, 22.0, 28.0, 21.5, 4);

-- Trainer insegna corsi
INSERT INTO teaching (trainerID, courseID) VALUES
(2, 1),
(2, 2),
(2, 3);

-- Iscrizioni corsi (più iscritti)
INSERT INTO enrollment (customerID, courseID, enrollmentDate) VALUES
(3, 1, CURDATE()),
(4, 2, DATE('2025-06-15')),
(5, 1, DATE('2025-06-20')),
(6, 3, DATE('2025-05-25')),
(5, 3, DATE('2025-04-01'));

-- Servicing
INSERT INTO servicing (maintenanceID, equipmentID) VALUES
(1, 2);