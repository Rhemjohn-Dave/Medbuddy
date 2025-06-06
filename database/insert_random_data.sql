-- Insert random users
INSERT INTO users (username, email, password, role, approval_status) VALUES
('dr.johnson', 'dr.johnson@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved'),
('dr.williams', 'dr.williams@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved'),
('dr.brown', 'dr.brown@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'approved'),
('patient1', 'patient1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved'),
('patient2', 'patient2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved'),
('patient3', 'patient3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'approved'),
('staff2', 'staff2@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved'),
('staff3', 'staff3@medbuddy.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 'approved');

-- Insert random doctors
INSERT INTO doctors (user_id, specialization_id, first_name, middle_name, last_name, license_number, contact_number, address, status) VALUES
(5, 2, 'Michael', 'James', 'Johnson', 'MD234567', '2345678901', '789 Doctor Lane, Medical City', 'active'),
(6, 3, 'Emily', 'Rose', 'Williams', 'MD345678', '3456789012', '456 Health Street, Medical City', 'active'),
(7, 4, 'David', 'Thomas', 'Brown', 'MD456789', '4567890123', '123 Medical Avenue, Medical City', 'active');

-- Insert random patients
INSERT INTO patients (user_id, first_name, middle_name, last_name, date_of_birth, gender, blood_type, contact_number, address, emergency_contact_name, emergency_contact_number, medical_history, allergies) VALUES
(8, 'Robert', 'Allen', 'Wilson', '1985-05-15', 'male', 'A+', '5678901234', '321 Patient Street, Medical City', 'Mary Wilson', '5678901235', 'Hypertension since 2010', 'Penicillin'),
(9, 'Lisa', 'Marie', 'Anderson', '1992-08-22', 'female', 'B-', '6789012345', '654 Health Road, Medical City', 'John Anderson', '6789012346', 'Type 2 Diabetes', 'None'),
(10, 'James', 'William', 'Taylor', '1978-11-30', 'male', 'O-', '7890123456', '987 Wellness Lane, Medical City', 'Sarah Taylor', '7890123457', 'Asthma', 'Shellfish');

-- Insert random staff
INSERT INTO staff (user_id, first_name, middle_name, last_name, position, contact_number, address, assigned_doctor_id) VALUES
(11, 'Jennifer', 'Ann', 'Davis', 'Nurse', '8901234567', '147 Medical Center Dr', 2),
(12, 'Robert', 'Lee', 'Miller', 'Receptionist', '9012345678', '258 Health Avenue', 3);

-- Insert random clinics
INSERT INTO clinics (name, address, phone, email, status) VALUES
('MedBuddy East Clinic', '789 East Medical Blvd', '555-0103', 'east@medbuddy.com', 'active'),
('MedBuddy West Clinic', '321 West Health Street', '555-0104', 'west@medbuddy.com', 'active');

-- Link doctors to clinics
INSERT INTO doctor_clinics (doctor_id, clinic_id) VALUES
(2, 1), (2, 2), (2, 3),
(3, 1), (3, 3),
(4, 2), (4, 4);

-- Insert random doctor schedules
INSERT INTO doctor_schedules (doctor_id, clinic_id, day_of_week, start_time, end_time, break_start, break_end, max_appointments_per_slot) VALUES
(2, 1, 3, '08:00:00', '16:00:00', '12:00:00', '13:00:00', 2),
(2, 2, 5, '09:00:00', '17:00:00', '12:30:00', '13:30:00', 2),
(3, 1, 4, '10:00:00', '18:00:00', '13:00:00', '14:00:00', 2),
(3, 3, 6, '08:30:00', '16:30:00', '12:00:00', '13:00:00', 2),
(4, 2, 2, '09:30:00', '17:30:00', '12:30:00', '13:30:00', 2),
(4, 4, 5, '08:00:00', '16:00:00', '12:00:00', '13:00:00', 2);

-- Insert random appointments
INSERT INTO appointments (patient_id, doctor_id, clinic_id, date, time, purpose, notes, status, vitals_recorded) VALUES
(1, 2, 1, '2024-03-20', '09:00:00', 'Regular checkup', 'Annual physical examination', 'scheduled', 0),
(2, 3, 1, '2024-03-21', '10:30:00', 'Follow-up consultation', 'Review blood test results', 'scheduled', 0),
(3, 4, 2, '2024-03-22', '14:00:00', 'Initial consultation', 'New patient visit', 'scheduled', 0);

-- Insert random medical records
INSERT INTO medical_records (patient_id, doctor_id, appointment_id, record_date, record_time, record_type, chief_complaint, present_illness, past_medical_history, family_history, social_history, allergies, medications, vital_signs, physical_examination, diagnosis, treatment_plan, prescription, notes) VALUES
(1, 2, 1, '2024-03-20', '09:00:00', 'initial', 'High blood pressure', 'Patient reports elevated BP readings', 'Hypertension since 2010', 'Father had heart disease', 'Non-smoker, occasional alcohol', 'Penicillin', 'Lisinopril 10mg daily', '{"bp": "140/90", "hr": "72", "temp": "98.6"}', 'Normal physical examination', 'Hypertension', 'Continue current medication', 'Lisinopril 10mg daily', 'Patient shows improvement'),
(2, 3, 2, '2024-03-21', '10:30:00', 'follow-up', 'Blood sugar control', 'Blood glucose levels elevated', 'Type 2 Diabetes', 'Mother has diabetes', 'Sedentary lifestyle', 'None', 'Metformin 500mg BID', '{"bp": "130/85", "hr": "75", "temp": "98.4"}', 'Normal physical examination', 'Type 2 Diabetes', 'Adjust medication dosage', 'Metformin 1000mg BID', 'Regular monitoring required'),
(3, 4, 3, '2024-03-22', '14:00:00', 'initial', 'Shortness of breath', 'Wheezing and chest tightness', 'Asthma since childhood', 'No family history', 'Non-smoker', 'Shellfish', 'Albuterol PRN', '{"bp": "120/80", "hr": "78", "temp": "98.2"}', 'Wheezing on auscultation', 'Asthma', 'Start maintenance inhaler', 'Albuterol 90mcg PRN', 'Avoid triggers');

-- Insert random vital signs
INSERT INTO vital_signs (medical_record_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, respiratory_rate, temperature, oxygen_saturation, weight, height, bmi, pain_scale, recorded_by) VALUES
(1, 140, 90, 72, 16, 98.6, 98.0, 75.5, 175.0, 24.6, 0, 11),
(2, 130, 85, 75, 18, 98.4, 97.5, 68.2, 165.0, 25.0, 0, 11),
(3, 120, 80, 78, 20, 98.2, 96.0, 70.0, 170.0, 24.2, 2, 11);

-- Insert random medical history
INSERT INTO medical_history (patient_id, condition_name, diagnosis_date, status, severity, notes) VALUES
(1, 'Hypertension', '2010-01-15', 'chronic', 'moderate', 'Well controlled with medication'),
(2, 'Type 2 Diabetes', '2015-06-20', 'chronic', 'moderate', 'Requires regular monitoring'),
(3, 'Asthma', '1990-03-10', 'chronic', 'mild', 'Triggered by exercise and cold weather');

-- Insert random allergies
INSERT INTO allergies (patient_id, allergen, reaction, severity, notes) VALUES
(1, 'Penicillin', 'Rash and hives', 'moderate', 'Avoid all penicillin-based antibiotics'),
(3, 'Shellfish', 'Anaphylaxis', 'severe', 'Carry EpiPen at all times');

-- Insert random medications
INSERT INTO medications (patient_id, medication_name, dosage, frequency, start_date, end_date, prescribed_by, status, notes) VALUES
(1, 'Lisinopril', '10mg', 'Once daily', '2024-01-01', NULL, 2, 'active', 'Take in the morning'),
(2, 'Metformin', '500mg', 'Twice daily', '2024-02-01', NULL, 3, 'active', 'Take with meals'),
(3, 'Albuterol', '90mcg', 'As needed', '2024-03-01', NULL, 4, 'active', 'Use before exercise');

-- Insert random immunizations
INSERT INTO immunizations (patient_id, vaccine_name, administration_date, administered_by, lot_number, next_due_date, notes) VALUES
(1, 'Influenza', '2023-10-15', 11, 'FLU2023-123', '2024-10-15', 'Annual flu shot'),
(2, 'Tetanus', '2022-05-20', 11, 'TET2022-456', '2027-05-20', 'Tdap booster'),
(3, 'COVID-19', '2023-01-10', 11, 'COVID2023-789', '2024-01-10', 'Annual booster');

-- Insert random notifications
INSERT INTO notifications (user_id, title, message, type) VALUES
(8, 'Appointment Reminder', 'Your appointment is scheduled for tomorrow at 9:00 AM', 'appointment'),
(9, 'Test Results', 'Your lab results are now available', 'system'),
(10, 'Prescription Update', 'Your prescription has been renewed', 'alert');

-- Insert random messages
INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES
(8, 5, 'Question about medication', 'Hello doctor, I have a question about my prescription'),
(9, 6, 'Appointment request', 'I would like to schedule an appointment next week'),
(10, 7, 'Follow-up query', 'Regarding my last visit, I have some concerns'); 