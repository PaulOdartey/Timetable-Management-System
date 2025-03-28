-- 1. DROP & CREATE DATABASE

DROP DATABASE IF EXISTS TimetableDB;
CREATE DATABASE TimetableDB;
USE TimetableDB;


-- 2. TABLE: Users

CREATE TABLE Users (
    UserID INT PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(100) NOT NULL,
    Email VARCHAR(100) UNIQUE NOT NULL,
    Role ENUM('Admin', 'AcademicOfficer', 'Instructor', 'Student') NOT NULL
);

-- Insert 6 Users
INSERT INTO Users (Name, Email, Role) 
VALUES 
    ('Paul Odartey', 'podartey@st.ug.edu.gh', 'Admin'),
    ('Efo Johnson', 'ejohnson@st.ug.edu.gh', 'AcademicOfficer'),
    ('James Ackah', 'jackah@st.ug.edu.gh', 'Instructor'),
    ('Michael Provencal', 'mprovencal@st.ug.edu.gh', 'Student'),
    ('Linda Mensah', 'lmensah@st.ug.edu.gh', 'Student'),
    ('Kwesi Asante', 'kasante@st.ug.edu.gh', 'Student');


-- 3. TABLE: Courses

CREATE TABLE Courses (
    CourseID INT PRIMARY KEY AUTO_INCREMENT,
    CourseName VARCHAR(100) NOT NULL,
    Credits INT NOT NULL,
    MaxStudents INT NOT NULL,
    RoomTypeRequired ENUM('Lab', 'Lecture') NOT NULL
);

-- Insert 3 Courses

INSERT INTO Courses (CourseName, Credits, MaxStudents, RoomTypeRequired) 
VALUES 
    ('Database Systems', 3, 50, 'Lecture'),
    ('Software Engineering', 4, 40, 'Lab'),
    ('Digital Forensics', 3, 60, 'Lecture');

-- 4. TABLE: Rooms

CREATE TABLE Rooms (
    RoomID INT PRIMARY KEY AUTO_INCREMENT,
    RoomName VARCHAR(50) NOT NULL,
    Capacity INT NOT NULL,
    RoomType ENUM('Lab', 'Lecture') NOT NULL
);

-- Insert 3 rooms

INSERT INTO Rooms (RoomName, Capacity, RoomType)
VALUES
    ('JQB', 100, 'Lecture'),
    ('Lab B1', 30, 'Lab'),
    ('KA Busia', 80, 'Lecture');

-- 5. TABLE: Instructors

CREATE TABLE Instructors (
    InstructorID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    Department VARCHAR(100) NOT NULL,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);

-- Insert 3 instructors
-- Linking them to Users #1, #2, and #3

INSERT INTO Instructors (UserID, Department)
VALUES
    (1, 'IT Management'),        -- Paul Odartey (Admin)
    (2, 'Computer Science'),     -- Efo Johnson (AcademicOfficer)
    (3, 'Software Engineering'); -- James Ackah (Instructor)

-- 6. TABLE: Students

CREATE TABLE Students (
    StudentID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    Program VARCHAR(100) NOT NULL,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);

-- Insert 3 students

INSERT INTO Students (UserID, Program)
VALUES
    (4, 'BSc Computer Science'),    -- Michael Provencal
    (5, 'BSc Information Technology'), 
    (6, 'BSc Software Engineering');

-- 7. TABLE: TimetableEntries

CREATE TABLE TimetableEntries (
    EntryID INT PRIMARY KEY AUTO_INCREMENT,
    CourseID INT NOT NULL,
    RoomID INT NOT NULL,
    InstructorID INT NOT NULL,
    Day ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
    StartTime TIME NOT NULL,
    EndTime TIME NOT NULL,
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID) ON DELETE CASCADE,
    FOREIGN KEY (RoomID) REFERENCES Rooms(RoomID) ON DELETE CASCADE,
    FOREIGN KEY (InstructorID) REFERENCES Instructors(InstructorID) ON DELETE CASCADE
);

-- Insert 3 timetable entries

INSERT INTO TimetableEntries (CourseID, RoomID, InstructorID, Day, StartTime, EndTime)
VALUES
    (1, 1, 1, 'Mon', '09:00:00', '11:00:00'),  -- DB Systems in JQB w/ InstructorID=1
    (2, 2, 2, 'Tue', '10:00:00', '12:00:00'),  -- Soft Eng in Lab B1 w/ InstructorID=2
    (3, 3, 3, 'Wed', '09:00:00', '11:00:00');  -- Digital Forensics in KA Busia w/ Instr=3

-- 8. TABLE: Enrollments

CREATE TABLE Enrollments (
    EnrollmentID INT PRIMARY KEY AUTO_INCREMENT,
    UserID INT NOT NULL,
    CourseID INT NOT NULL,
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE,
    FOREIGN KEY (CourseID) REFERENCES Courses(CourseID) ON DELETE CASCADE,
    UNIQUE (UserID, CourseID)
);

-- Insert sample enrollments

INSERT INTO Enrollments (UserID, CourseID)
VALUES
    (4, 1),  -- e.g., User with UserID 4 enrolled in CourseID 1
    (5, 2),  -- e.g., User with UserID 5 enrolled in CourseID 2
    (6, 3);  -- e.g., User with UserID 6 enrolled in CourseID 3


-- 9. TABLE: ChangeRequest

CREATE TABLE ChangeRequest (
    RequestID INT PRIMARY KEY AUTO_INCREMENT,
    InstructorID INT NOT NULL,
    EntryID INT NOT NULL,
    Reason VARCHAR(255) NOT NULL,
    Status ENUM('Pending','Approved','Rejected') NOT NULL,
    FOREIGN KEY (InstructorID) REFERENCES Instructors(InstructorID) ON DELETE CASCADE,
    FOREIGN KEY (EntryID) REFERENCES TimetableEntries(EntryID) ON DELETE CASCADE
);

-- Insert 3 sample change requests

INSERT INTO ChangeRequest (InstructorID, EntryID, Reason, Status)
VALUES
    (1, 1, 'Need to switch room for practicals', 'Pending'),
    (2, 2, 'Clash with departmental meeting', 'Pending'),
    (3, 3, 'Reschedule for personal reasons', 'Pending');



-- Example Queries

-- Q1: List all users

SELECT * FROM Users;

-- Q2: Show all students (join Users + Students)

SELECT s.StudentID, u.Name, u.Email, s.Program
FROM Students s
JOIN Users u ON s.UserID = u.UserID;

-- Q3: Display timetable with course, room, instructor names

SELECT t.EntryID, c.CourseName, r.RoomName, i.InstructorID, u.Name AS InstructorName,
       t.Day, t.StartTime, t.EndTime
FROM TimetableEntries t
JOIN Courses c ON t.CourseID = c.CourseID
JOIN Rooms r ON t.RoomID = r.RoomID
JOIN Instructors i ON t.InstructorID = i.InstructorID
JOIN Users u ON i.UserID = u.UserID;

-- Q4: List all change requests with instructor & entry info

SELECT cr.RequestID, u.Name AS InstructorName, c.CourseName, cr.Reason, cr.Status
FROM ChangeRequest cr
JOIN Instructors i ON cr.InstructorID = i.InstructorID
JOIN Users u ON i.UserID = u.UserID
JOIN TimetableEntries te ON cr.EntryID = te.EntryID
JOIN Courses c ON te.CourseID = c.CourseID;

-- Query 5: Count Total Enrollments for Each Course

SELECT c.CourseName, COUNT(e.EnrollmentID) AS TotalEnrollments
FROM Courses c
LEFT JOIN Enrollments e ON c.CourseID = e.CourseID
GROUP BY c.CourseName;

-- Query 6: List Timetable Entries for a Specific Day (e.g., Tuesday)

SELECT te.EntryID, c.CourseName, r.RoomName, u.Name AS InstructorName, te.Day, te.StartTime, te.EndTime
FROM TimetableEntries te
JOIN Courses c ON te.CourseID = c.CourseID
JOIN Rooms r ON te.RoomID = r.RoomID
JOIN Instructors i ON te.InstructorID = i.InstructorID
JOIN Users u ON i.UserID = u.UserID
WHERE te.Day = 'Tue';

