USE gymdb;

INSERT INTO USERS (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role) VALUES
('admin1@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'admin1User', 'Alice', 'Rossi', '1980-01-01', 'F', '3901234567', 'admin'),
('admin2@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'admin2User', 'Alice', 'Rossi', '1980-01-01', 'F', '3901234567', 'admin');

INSERT INTO USERS (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role) VALUES
('trainer1@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'fitTrainer1', 'Marco', 'Bianchi', '1990-05-15', 'M', '3902345678', 'trainer'),
('trainer2@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'fitTrainer2', 'Sara', 'Verdi', '1988-08-22', 'F', '3903456789', 'trainer');

INSERT INTO USERS (email, password, userName, firstName, lastName, birthDate, gender, phoneNumber, role) VALUES
('customer1@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'john_doe', 'Giovanni', 'Neri', '1995-03-20', 'M', '3904567890', 'customer'),
('customer2@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'jane_smith', 'Giulia', 'Ferrari', '1992-07-10', 'F', '3905678901', 'customer'),
('customer3@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'mike_brown', 'Michele', 'Romano', '1988-11-05', 'M', '3906789012', 'customer'),
('customer4@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'emma_white', 'Emma', 'Greco', '1990-02-25', 'F', '3907890123', 'customer'),
('customer5@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'luca_blu', 'Luca', 'Blu', '1993-01-15', 'M', '3908901234', 'customer'),
('customer6@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'anna_giallo', 'Anna', 'Giallo', '1991-06-08', 'F', '3909012345', 'customer'),
('customer7@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'paolo_viola', 'Paolo', 'Viola', '1987-09-12', 'M', '3900123456', 'customer'),
('customer8@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'lucia_rosa', 'Lucia', 'Rosa', '1994-04-18', 'F', '3901234568', 'customer'),
('customer9@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'andrea_oro', 'Andrea', 'Oro', '1989-12-03', 'M', '3902345679', 'customer'),
('customer10@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'sofia_argento', 'Sofia', 'Argento', '1996-05-30', 'F', '3903456780', 'customer'),
('customer11@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'matteo_bronzo', 'Matteo', 'Bronzo', '1985-10-14', 'M', '3904567891', 'customer'),
('customer12@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'chiara_rame', 'Chiara', 'Rame', '1992-03-27', 'F', '3905678902', 'customer'),
('customer13@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'davide_ferro', 'Davide', 'Ferro', '1990-08-09', 'M', '3906789013', 'customer'),
('customer14@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'valeria_acciaio', 'Valeria', 'Acciaio', '1993-01-21', 'F', '3907890124', 'customer'),
('customer15@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'francesco_titanio', 'Francesco', 'Titanio', '1986-06-16', 'M', '3908901235', 'customer'),
('customer16@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'martina_cromo', 'Martina', 'Cromo', '1994-11-02', 'F', '3909012346', 'customer'),
('customer17@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'alessandro_piombo', 'Alessandro', 'Piombo', '1991-04-07', 'M', '3900123457', 'customer'),
('customer18@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'federica_stagno', 'Federica', 'Stagno', '1988-09-25', 'F', '3901234569', 'customer'),
('customer19@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'simone_zinco', 'Simone', 'Zinco', '1995-02-13', 'M', '3902345680', 'customer'),
('customer20@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'elena_nichel', 'Elena', 'Nichel', '1987-07-19', 'F', '3903456781', 'customer'),
('customer21@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'roberto_cobalto', 'Roberto', 'Cobalto', '1992-12-05', 'M', '3904567892', 'customer'),
('customer22@gym.com', '$2y$10$B4qUjLDwlqb2pcze28NtPeKvLpL7YAselMrTZBWwW.ux28FSdS1lK', 'silvia_magnesio', 'Silvia', 'Magnesio', '1989-05-11', 'F', '3905678903', 'customer');

INSERT INTO EQUIPMENTS (name, description, status, administratorID) VALUES
('Tapis roulant', 'Tapis roulant elettrico con varie velocità', 'available', 1),
('Cyclette', 'Bicicletta statica per esercizi cardio', 'maintenance', 1),
('Panca Piana', 'Panca per sollevamento pesi', 'available', 1),
('Manubri Set', 'Set completo di manubri fino a 40kg', 'broken', 1),
('Ellittica Pro', 'Macchina cardio multifunzione', 'available', 1),
('Vogatore Magnetico', 'Vogatore con resistenza magnetica', 'available', 1),
('Panca Inclinata', 'Panca regolabile per esercizi vari', 'maintenance', 1),
('Kettlebell Set', 'Set da 5 kettlebell (4-20kg)', 'available', 1),
('Lat Machine', 'Macchina per trazioni dorsali', 'available', 1),
('Leg Press', 'Macchina per allenamento gambe', 'broken', 1),
('Cavo Incrociato', 'Macchina per esercizi ai cavi', 'available', 1),
('Panca per Dip', 'Panca per dip e addominali', 'maintenance', 1);

INSERT INTO COURSES (name, description, maxParticipants, startDate, finishDate) VALUES
('Yoga Base', 'Corso base di Yoga per principianti', 15, '2025-07-01', '2025-09-30'),
('Pilates Avanzato', 'Corso avanzato di Pilates', 10, '2025-07-15', '2025-10-15'),
('Functional Training', 'Allenamento funzionale per tutti i livelli', 12, '2025-08-01', '2025-10-31'),
('HIIT Morning', 'Allenamento ad alta intensità al mattino', 8, '2025-07-20', '2025-09-30'),
('Zumba Fitness', 'Lezione di ballo fitness', 25, '2025-08-10', '2025-10-20'),
('Pump Total Body', 'Allenamento con bilanciere', 15, '2025-07-05', '2025-09-15'),
('Cycling Indoor', 'Cicletta di gruppo', 20, '2025-08-15', '2025-11-15'),
('Kickboxing', 'Arti marziali fitness', 18, '2025-07-25', '2025-10-05'),
('Stretching Posturale', 'Miglioramento mobilità articolare', 20, '2025-08-05', '2025-10-25'),
('Cross Training', 'Allenamento misto forza e resistenza', 15, '2025-07-30', '2025-10-10'),
('Danza Classica', 'Corso base di danza', 12, '2025-08-20', '2025-11-30'),
('Body Building', 'Sviluppo muscolare', 10, '2025-07-10', '2025-09-20');

INSERT INTO EXERCISES (name, description, trainerID) VALUES
('Squat', 'Esercizio per gambe e glutei', 2),
('Push-up', 'Esercizio per petto e braccia', 2),
('Deadlift', 'Sollevamento da terra', 2),
('Plank', 'Esercizio di tenuta addominale', 2),
('Lunges', 'Affondi con manubri', 3),
('Bench Press', 'Panca piana con bilanciere', 3),
('Pull-ups', 'Trazioni alla sbarra', 4),
('Bicep Curls', 'Ricci per bicipiti', 4),
('Russian Twist', 'Addominali rotazionali', 3),
('Calf Raises', 'Flessioni plantari', 4),
('Shoulder Press', 'Spinte sopra la testa', 3),
('Leg Curl', 'Flessioni posteriori', 4);

INSERT INTO MAINTENANCES (equipmentID, maintenanceDate, maintenanceCost, description, status) VALUES
(2, '2025-06-25', 100.00, 'Sostituzione cinghia', 'scheduled'),
(4, '2025-07-05', 50.00, 'Riparazione impugnature', 'in_progress'),
(1, '2025-07-10', 75.00, 'Lubrificazione nastro', 'completed'),
(3, '2025-07-15', 40.00, 'Serraggio viti', 'completed'),
(5, '2025-07-20', 200.00, 'Sostituzione cuscinetti', 'scheduled'),
(7, '2025-07-25', 90.00, 'Calibrazione pesi', 'in_progress'),
(8, '2025-07-28', 60.00, 'Sostituzione componenti usurate', 'scheduled');

INSERT INTO AVAILABILITY_DAYS (trainerID, dayOfWeek, startTime, finishTime) VALUES
(2, 'Monday', '09:00:00', '17:00:00'),
(2, 'Wednesday', '09:00:00', '17:00:00'),
(2, 'Friday', '14:00:00', '18:00:00'),
(3, 'Tuesday', '08:00:00', '16:00:00'),
(3, 'Thursday', '10:00:00', '18:00:00'),
(3, 'Saturday', '09:00:00', '13:00:00'),
(4, 'Monday', '14:00:00', '20:00:00'),
(4, 'Wednesday', '12:00:00', '19:00:00'),
(4, 'Friday', '07:00:00', '15:00:00');

INSERT INTO TRAINING_SCHEDULES (name, description, creationDate, customerID, trainerID) VALUES
('Piano base John', 'Programma di allenamento per principianti', CURDATE(), 3, 2),
('Piano avanzato Jane', 'Programma intensivo avanzato', CURDATE(), 4, 2),
('Piano massa Luca', 'Programma ipertrofia', CURDATE(), 5, 3),
('Piano definizione Anna', 'Programma cutting', CURDATE(), 6, 4),
('Piano forza Paolo', 'Powerlifting base', CURDATE(), 7, 3),
('Piano completo Lucia', 'Allenamento full body', CURDATE(), 8, 4);

INSERT INTO TRAINING_DAYS (name, description, trainingScheduleID, dayOrder) VALUES
('Giorno 1', 'Focus gambe e core', 1, 1),
('Giorno 2', 'Upper body', 1, 2),
('Giorno 1', 'Full Body', 2, 1),
('Giorno 3', 'Cardio e mobilità', 1, 3),
('Giorno 2', 'Lower body', 2, 2),
('Giorno 3', 'Core e stabilizzazione', 2, 3),
('Giorno 1', 'Push day', 3, 1),
('Giorno 2', 'Pull day', 3, 2),
('Giorno 3', 'Leg day', 3, 3),
('Giorno 1', 'Upper body', 4, 1),
('Giorno 2', 'Lower body', 4, 2),
('Giorno 3', 'Active recovery', 4, 3);

INSERT INTO EXERCISE_DETAILS (sets, reps, weight, restTime, trainingDayID, exerciseID, orderInWorkout) VALUES
(3, 12, 50, 60, 1, 1, 1),
(4, 15, NULL, 45, 2, 2, 1),
(4, 8, 80, 90, 3, 3, 1),
(3, 1, NULL, 120, 3, 4, 2),
(4, 10, 30, 90, 4, 5, 1),
(3, 12, 20, 60, 5, 6, 2),
(5, 5, 100, 180, 6, 7, 1),
(3, 15, NULL, 45, 7, 8, 3),
(4, 8, 40, 120, 8, 5, 1),
(3, 10, 25, 90, 9, 6, 2),
(5, 12, 70, 60, 10, 7, 1),
(4, 10, 50, 90, 11, 8, 2);

INSERT INTO MEMBERSHIPS (name, price, duration, description) VALUES
('Abbonamento Mensile', 30.00, 30, 'Accesso illimitato per un mese'),
('Abbonamento Annuale', 300.00, 365, 'Accesso illimitato per un anno'),
('Abbonamento Trimestrale', 80.00, 90, 'Accesso illimitato per tre mesi'),
('Abbonamento Settimanale', 10.00, 7, 'Accesso 7 giorni'),
('Premium Mensile', 50.00, 30, 'Accesso + corsi illimitati'),
('Premium Annuale', 500.00, 365, 'Accesso + corsi + personal trainer'),
('Student Mensile', 20.00, 30, 'Per studenti universitari'),
('Offerta Pensionati', 25.00, 30, 'Sconto over 65'),
('Family Pack', 70.00, 30, '2 adulti + 2 ragazzi');

INSERT INTO PROMOTIONS (name, description, discountRate, startDate, expirationDate) VALUES
('Promo Estate', 'Sconto estivo del 20%', 20.00, '2025-06-01', '2025-08-31'),
('Back to Gym', 'Sconto autunnale del 15%', 15.00, '2025-09-01', '2025-11-30'),
('Black Friday', 'Sconto del 30%', 30.00, '2025-11-25', '2025-11-30'),
('Natale 2025', 'Promozione festività', 25.00, '2025-12-20', '2026-01-10'),
('Primavera 2026', 'Rinnovo abbonamenti', 15.00, '2026-03-01', '2026-03-31'),
('Benvenuto ESTATE', 'Sconto nuovo iscritto', 40.00, '2025-06-15', '2025-07-15');

INSERT INTO SUBSCRIPTIONS (startDate, expirationDate, customerID, promotionID, membershipID) VALUES
('2025-06-01', '2025-06-30', 3, 1, 1),
('2025-07-01', '2025-07-03', 3, NULL, 2),
('2025-06-10', '2025-09-08', 4, 1, 3),
('2025-07-01', '2026-06-30', 5, NULL, 2),
('2025-07-01', '2025-09-30', 6, 2, 3),
('2025-07-16', '2026-08-16', 3, NULL, 2),
('2025-07-05', '2025-08-04', 7, 3, 1),
('2025-07-10', '2025-10-08', 8, 1, 3),
('2025-07-15', '2026-07-14', 9, NULL, 2),
('2025-07-20', '2025-08-19', 10, 4, 4),
('2025-08-01', '2025-10-30', 11, 2, 3),
('2025-08-05', '2026-08-04', 12, NULL, 2),
('2025-08-10', '2025-09-09', 13, 1, 1),
('2025-08-15', '2025-11-13', 14, 3, 3),
('2025-08-20', '2026-08-19', 15, NULL, 2),
('2025-09-01', '2025-10-01', 16, 4, 4),
('2025-09-05', '2025-12-04', 17, 2, 3),
('2025-09-10', '2026-09-09', 18, NULL, 2);

INSERT INTO PAYMENTS (date, amount, customerID, subscriptionID) VALUES
('2025-06-01', 69.00, 3, 1),
('2025-07-01', 300.00, 3, 2),
('2025-06-10', 80.00, 4, 3),
('2025-07-01', 300.00, 5, 4),
('2025-07-01', 80.00, 6, 5),
('2025-07-01', 300.00, 3, 6),
('2025-07-05', 10.00, 7, 7),
('2025-07-10', 80.00, 8, 8),
('2025-07-15', 300.00, 9, 9),
('2025-07-20', 20.00, 10, 10),
('2025-08-01', 80.00, 11, 11),
('2025-08-05', 300.00, 12, 12),
('2025-08-10', 10.00, 13, 13),
('2025-08-15', 80.00, 14, 14),
('2025-08-20', 300.00, 15, 15),
('2025-09-01', 20.00, 16, 16),
('2025-09-05', 80.00, 17, 17),
('2025-09-10', 300.00, 18, 18);

INSERT INTO FEEDBACKS (date, rating, comment, customerID) VALUES
('2025-06-21', 5, 'Ottima palestra!', 4),
('2025-06-22', 4, 'Personale gentile.', 5),
('2025-07-01', 3, 'Potrebbero migliorare gli spogliatoi.', 6),
('2025-07-05', 5, 'Attrezzature sempre perfette', 7),
('2025-07-10', 4, 'Corsi ben strutturati', 8),
('2025-07-15', 3, 'Prezzi un po alti', 9),
('2025-07-20', 5, 'Personal trainer eccezionale', 10),
('2025-08-01', 4, 'Ottima pulizia', 11),
('2025-08-05', 2, 'Poca disponibilità attrezzature', 12);

INSERT INTO PROGRESS_REPORTS (date, description, weight, bodyFatPercent, muscleMass, bmi, customerID) VALUES
('2025-06-15', 'Prima valutazione', 80.00, 18.5, 30.0, 24.0, 3),
('2025-06-18', 'Valutazione mensile', 68.00, 22.0, 28.0, 21.5, 4),
('2025-07-05', 'Controllo progressi estate', 85.00, 17.0, 32.0, 25.5, 5);

INSERT INTO TEACHINGS (trainerID, courseID) VALUES
(2, 1),
(2, 2),
(2, 3),
(2, 4);

INSERT INTO ENROLLMENTS (customerID, courseID) VALUES
(3, 1),
(4, 2),
(5, 3),
(6, 1),
(6, 4);
