-- Create medical_records table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT,
    record_date DATE NOT NULL,
    record_time TIME NOT NULL,
    record_type ENUM('initial', 'follow-up', 'emergency') NOT NULL,
    chief_complaint TEXT,
    present_illness TEXT,
    past_medical_history TEXT,
    family_history TEXT,
    social_history TEXT,
    allergies TEXT,
    medications TEXT,
    vital_signs JSON,
    physical_examination TEXT,
    diagnosis TEXT,
    treatment_plan TEXT,
    prescription TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Create vital_signs table for detailed vital signs tracking
CREATE TABLE IF NOT EXISTS vital_signs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    medical_record_id INT NOT NULL,
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    heart_rate INT,
    respiratory_rate INT,
    temperature DECIMAL(4,2),
    oxygen_saturation DECIMAL(5,2),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    bmi DECIMAL(5,2),
    pain_scale INT,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES staff(id) ON DELETE CASCADE
);

-- Create medical_history table for patient's medical history
CREATE TABLE IF NOT EXISTS medical_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    condition_name VARCHAR(100) NOT NULL,
    diagnosis_date DATE,
    status ENUM('active', 'resolved', 'chronic') NOT NULL,
    severity ENUM('mild', 'moderate', 'severe') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Create allergies table
CREATE TABLE IF NOT EXISTS allergies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    allergen VARCHAR(100) NOT NULL,
    reaction TEXT,
    severity ENUM('mild', 'moderate', 'severe') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Create medications table
CREATE TABLE IF NOT EXISTS medications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    medication_name VARCHAR(100) NOT NULL,
    dosage VARCHAR(50) NOT NULL,
    frequency VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    prescribed_by INT NOT NULL,
    status ENUM('active', 'discontinued', 'completed') NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (prescribed_by) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Create immunizations table
CREATE TABLE IF NOT EXISTS immunizations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    vaccine_name VARCHAR(100) NOT NULL,
    administration_date DATE NOT NULL,
    administered_by INT NOT NULL,
    lot_number VARCHAR(50),
    next_due_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (administered_by) REFERENCES staff(id) ON DELETE CASCADE
);

-- Add indexes for better performance
CREATE INDEX idx_medical_records_patient ON medical_records(patient_id);
CREATE INDEX idx_medical_records_doctor ON medical_records(doctor_id);
CREATE INDEX idx_medical_records_appointment ON medical_records(appointment_id);
CREATE INDEX idx_vital_signs_medical_record ON vital_signs(medical_record_id);
CREATE INDEX idx_medical_history_patient ON medical_history(patient_id);
CREATE INDEX idx_allergies_patient ON allergies(patient_id);
CREATE INDEX idx_medications_patient ON medications(patient_id);
CREATE INDEX idx_immunizations_patient ON immunizations(patient_id); 