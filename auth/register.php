<?php
/**
 * User Registration Form
 * Timetable Management System
 * 
 * Professional registration interface for faculty and students
 * with comprehensive validation and responsive design
 */

// Define system access
define('SYSTEM_ACCESS', true);

// Include configuration files
require_once '../config/config.php';
require_once '../classes/User.php';

// Initialize user class
$user = new User();

// Check if already logged in
if (User::isLoggedIn() && !User::isSessionExpired()) {
    $redirectUrl = $user->getRedirectUrl(User::getCurrentUserRole());
    header('Location: ' . $redirectUrl);
    exit;
}

// Get departments for dropdown
$db = Database::getInstance();
$departments = $db->fetchAll("SELECT department_id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");

// Get registration type from URL
$registrationType = $_GET['type'] ?? 'student';
if (!in_array($registrationType, ['faculty', 'student'])) {
    $registrationType = 'student';
}

// Handle form submission
$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $errors['general'] = 'Invalid request. Please try again.';
    } else {
        // Collect form data
        $formData = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role' => $registrationType,
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            // Store both the raw id and the value expected by validation/profile helpers
            'department_id' => (int)($_POST['department_id'] ?? 0),
            'department' => (int)($_POST['department_id'] ?? 0),
            'phone' => trim($_POST['phone'] ?? ''),
        ];
        
        // Map department_id to department name for validation/profile helpers
        if ($formData['department_id'] > 0) {
            foreach ($departments as $dept) {
                if ($dept['department_id'] == $formData['department_id']) {
                    $formData['department'] = $dept['department_name'];
                    break;
                }
            }
        }
        
        // Role-specific data
        if ($registrationType === 'student') {
            $formData['student_number'] = trim($_POST['student_number'] ?? '');
            $formData['year_of_study'] = (int)($_POST['year_of_study'] ?? 1);
            $formData['semester'] = (int)($_POST['semester'] ?? 1);
        } elseif ($registrationType === 'faculty') {
            $formData['employee_id'] = trim($_POST['employee_id'] ?? '');
            $formData['designation'] = trim($_POST['designation'] ?? '');
            $formData['specialization'] = trim($_POST['specialization'] ?? '');
            // Ensure semester/year keys are not undefined later
            $formData['year_of_study'] = null;
            $formData['semester'] = null;
        }
        
        // Validate department selection
        if ($formData['department_id'] <= 0) {
            $errors['department_id'] = 'Please select a department.';
        }
        
        // Validate password confirmation
        if ($formData['password'] !== $formData['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        
        // Validate terms acceptance
        if (!isset($_POST['accept_terms'])) {
            $errors['accept_terms'] = 'You must accept the terms and conditions.';
        }
        
        // If no validation errors, attempt registration
        if (empty($errors)) {
            $registrationResult = $user->register($formData);
            
            if ($registrationResult['success']) {
                $success = $registrationResult['message'];
                $formData = []; // Clear form data on success
            } else {
                if (isset($registrationResult['errors'])) {
                    $errors = array_merge($errors, $registrationResult['errors']);
                } else {
                    $errors['general'] = $registrationResult['message'];
                }
            }
        }
    }
}

// Generate username suggestion
$usernameSuggestion = '';
if (!empty($formData['first_name']) && !empty($formData['last_name'])) {
    $usernameSuggestion = strtolower($formData['first_name'] . '.' . $formData['last_name']);
}

$pageTitle = ucfirst($registrationType) . ' Registration - ' . SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Enhanced Border Visibility Fix -->

    <!-- Font Awesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        
        .registration-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin: 20px auto;
            max-width: 800px;
        }
        
        .registration-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .role-badge.faculty {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .role-badge.student {
            background: linear-gradient(135deg, #007bff, #6f42c1);
            color: white;
        }
        
        .form-floating {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            display: block;
            margin-top: 5px;
            font-size: 0.875rem;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 123, 255, 0.4);
        }
        
        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 30px 0;
            position: relative;
        }
        
        .section-divider::before {
            content: attr(data-text);
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            padding: 0 20px;
            color: #6c757d;
            font-weight: 600;
        }
        
        .password-rules {
            margin-top: 8px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .password-rule {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 4px 0;
            font-size: 13px;
            color: #64748b;
        }

        .password-rule i {
            font-size: 12px;
        }

        .rule-valid {
            color: #10b981;
        }

        .rule-invalid {
            color: #64748b;
        }

        .password-strength-text {
            font-size: 13px;
            font-weight: 500;
            margin-top: 4px;
        }

        .strength-weak { color: #ef4444; }
        .strength-fair { color: #f59e0b; }
        .strength-good { color: #10b981; }
        .strength-strong { color: #059669; }
        
        .strength-bar {
            height: 4px;
            border-radius: 2px;
            background: #e2e8f0;
            margin-top: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .strength-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .fill-weak { background: #ef4444; width: 25%; }
        .fill-fair { background: #f59e0b; width: 50%; }
        .fill-good { background: #10b981; width: 75%; }
        .fill-strong { background: #059669; width: 100%; }
        
        .terms-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .registration-container {
                margin: 10px;
                padding: 30px 20px;
            }
            
            .row > div {
                margin-bottom: 20px;
            }
        }
        
        .loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .loading::after {
            content: '';
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Update password toggle button styles */
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 16px;
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            z-index: 10;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-toggle:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .form-floating {
            position: relative;
        }

        /* Adjust padding for password fields to accommodate the toggle button */
        .form-floating input[type="password"],
        .form-floating input[type="text"] {
            padding-right: 50px !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="registration-container">
            <!-- Header -->
            <div class="registration-header">
                <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                <h2 class="fw-bold text-dark">Create Your Account</h2>
                <div class="role-badge <?php echo $registrationType; ?>">
                    <i class="fas <?php echo $registrationType === 'faculty' ? 'fa-chalkboard-teacher' : 'fa-user-graduate'; ?> me-2"></i>
                    <?php echo ucfirst($registrationType); ?> Registration
                </div>
                <p class="text-muted">Join <?php echo SYSTEM_NAME; ?> and manage your academic schedule</p>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-2">
                        <a href="login.php" class="btn btn-success btn-sm">
                            <i class="fas fa-sign-in-alt me-1"></i>
                            Go to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Registration Form -->
            <?php if (empty($success)): ?>
            <form method="POST" action="" id="registrationForm" novalidate autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                
                <!-- Account Information Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Username"
                                   value=""
                                   autocomplete="username"
                                   required>
                            <label for="username">
                                <i class="fas fa-user me-2"></i>Username
                            </label>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['username']; ?></div>
                            <?php endif; ?>
                            <small class="text-muted">This will be used for login</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="email" 
                                   class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Email Address"
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                   required>
                            <label for="email">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Password Section -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Password"
                                   autocomplete="new-password"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password', 'passwordToggleIcon')" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="passwordToggleIcon"></i>
                            </button>
                            <label for="password">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                            <?php endif; ?>
                            <div class="password-rules" id="passwordRules">
                                <div class="password-rule" id="lengthRule">
                                    <i class="fas fa-circle"></i>
                                    <span>At least 8 characters long</span>
                                </div>
                                <div class="password-rule" id="uppercaseRule">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains uppercase letter</span>
                                </div>
                                <div class="password-rule" id="lowercaseRule">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains lowercase letter</span>
                                </div>
                                <div class="password-rule" id="numberRule">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains number</span>
                                </div>
                                <div class="password-rule" id="specialRule">
                                    <i class="fas fa-circle"></i>
                                    <span>Contains special character</span>
                                </div>
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthFill"></div>
                                </div>
                                <div class="password-strength-text" id="strengthText">Password Strength: Too Weak</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="password" 
                                   class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm Password"
                                   autocomplete="new-password"
                                   required>
                            <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', 'confirmPasswordToggleIcon')" aria-label="Toggle password visibility">
                                <i class="fas fa-eye" id="confirmPasswordToggleIcon"></i>
                            </button>
                            <label for="confirm_password">
                                <i class="fas fa-lock me-2"></i>Confirm Password
                            </label>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Personal Information Section -->
                <div class="section-divider" data-text="Personal Information"></div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['first_name']) ? 'is-invalid' : ''; ?>" 
                                   id="first_name" 
                                   name="first_name" 
                                   placeholder="First Name"
                                   value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                                   required>
                            <label for="first_name">
                                <i class="fas fa-user me-2"></i>First Name
                            </label>
                            <?php if (isset($errors['first_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['first_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['last_name']) ? 'is-invalid' : ''; ?>" 
                                   id="last_name" 
                                   name="last_name" 
                                   placeholder="Last Name"
                                   value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                                   required>
                            <label for="last_name">
                                <i class="fas fa-user me-2"></i>Last Name
                            </label>
                            <?php if (isset($errors['last_name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['last_name']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select <?php echo isset($errors['department_id']) ? 'is-invalid' : ''; ?>" 
                                    id="department_id" 
                                    name="department_id" 
                                    required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo (isset($formData['department_id']) && $formData['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="department_id">Department *</label>
                            <?php if (isset($errors['department_id'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['department_id']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   placeholder="Phone Number"
                                   value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
                            <label for="phone">
                                <i class="fas fa-phone me-2"></i>Phone Number (Optional)
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Role-Specific Fields -->
                <?php if ($registrationType === 'student'): ?>
                <div class="section-divider" data-text="Academic Information"></div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['student_number']) ? 'is-invalid' : ''; ?>" 
                                   id="student_number" 
                                   name="student_number" 
                                   placeholder="Student Number"
                                   value="<?php echo htmlspecialchars($formData['student_number'] ?? ''); ?>"
                                   required>
                            <label for="student_number">
                                <i class="fas fa-id-card me-2"></i>Student Number
                            </label>
                            <?php if (isset($errors['student_number'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['student_number']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-floating">
                            <select class="form-control <?php echo isset($errors['year_of_study']) ? 'is-invalid' : ''; ?>" 
                                    id="year_of_study" 
                                    name="year_of_study" 
                                    required>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($formData['year_of_study'] ?? 1) == $i ? 'selected' : ''; ?>>
                                        Year <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <label for="year_of_study">
                                <i class="fas fa-calendar me-2"></i>Year of Study
                            </label>
                            <?php if (isset($errors['year_of_study'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['year_of_study']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="form-floating">
                            <select class="form-control" 
                                    id="semester" 
                                    name="semester">
                                <?php for ($i = 1; $i <= 2; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($formData['semester'] ?? 1) == $i ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <label for="semester">
                                <i class="fas fa-calendar-alt me-2"></i>Current Semester
                            </label>
                        </div>
                    </div>
                </div>
                
                <?php elseif ($registrationType === 'faculty'): ?>
                <div class="section-divider" data-text="Professional Information"></div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" 
                                   class="form-control <?php echo isset($errors['employee_id']) ? 'is-invalid' : ''; ?>" 
                                   id="employee_id" 
                                   name="employee_id" 
                                   placeholder="Employee ID"
                                   value="<?php echo htmlspecialchars($formData['employee_id'] ?? ''); ?>"
                                   required>
                            <label for="employee_id">
                                <i class="fas fa-id-badge me-2"></i>Employee ID
                            </label>
                            <?php if (isset($errors['employee_id'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['employee_id']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-control <?php echo isset($errors['designation']) ? 'is-invalid' : ''; ?>" 
                                    id="designation" 
                                    name="designation" 
                                    required>
                                <option value="">Select Designation</option>
                                <option value="Professor" <?php echo ($formData['designation'] ?? '') === 'Professor' ? 'selected' : ''; ?>>Professor</option>
                                <option value="Associate Professor" <?php echo ($formData['designation'] ?? '') === 'Associate Professor' ? 'selected' : ''; ?>>Associate Professor</option>
                                <option value="Assistant Professor" <?php echo ($formData['designation'] ?? '') === 'Assistant Professor' ? 'selected' : ''; ?>>Assistant Professor</option>
                                <option value="Lecturer" <?php echo ($formData['designation'] ?? '') === 'Lecturer' ? 'selected' : ''; ?>>Lecturer</option>
                                <option value="Teaching Assistant" <?php echo ($formData['designation'] ?? '') === 'Teaching Assistant' ? 'selected' : ''; ?>>Teaching Assistant</option>
                            </select>
                            <label for="designation">
                                <i class="fas fa-user-tie me-2"></i>Designation
                            </label>
                            <?php if (isset($errors['designation'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['designation']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-floating">
                    <textarea class="form-control" 
                              id="specialization" 
                              name="specialization" 
                              placeholder="Specialization" 
                              style="height: 100px"><?php echo htmlspecialchars($formData['specialization'] ?? ''); ?></textarea>
                    <label for="specialization">
                        <i class="fas fa-star me-2"></i>Specialization/Areas of Expertise (Optional)
                    </label>
                </div>
                <?php endif; ?>
                
                <!-- Terms and Conditions -->
                <div class="terms-section">
                    <div class="form-check">
                        <input class="form-check-input <?php echo isset($errors['accept_terms']) ? 'is-invalid' : ''; ?>" 
                               type="checkbox" 
                               id="accept_terms" 
                               name="accept_terms" 
                               required>
                        <label class="form-check-label" for="accept_terms">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> 
                            and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                        </label>
                        <?php if (isset($errors['accept_terms'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['accept_terms']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Your account will be reviewed by an administrator before activation.
                        You will receive an email notification once approved.
                    </small>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary btn-register" id="submitBtn">
                    <i class="fas fa-user-plus me-2"></i>
                    Create Account
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Login Link -->
            <div class="login-link">
                <p class="text-muted">
                    Already have an account? 
                    <a href="login.php" class="text-decoration-none fw-semibold">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        Sign In Here
                    </a>
                </p>
                
                <div class="mt-3">
                    <a href="register.php?type=<?php echo $registrationType === 'faculty' ? 'student' : 'faculty'; ?>" 
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-<?php echo $registrationType === 'faculty' ? 'user-graduate' : 'chalkboard-teacher'; ?> me-1"></i>
                        Register as <?php echo $registrationType === 'faculty' ? 'Student' : 'Faculty'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Account Usage</h6>
                    <p>You agree to use your account responsibly and in accordance with academic policies.</p>
                    
                    <h6>2. Data Accuracy</h6>
                    <p>You are responsible for providing accurate and up-to-date information during registration.</p>
                    
                    <h6>3. System Access</h6>
                    <p>Access to the system is subject to administrator approval and institutional policies.</p>
                    
                    <h6>4. Privacy</h6>
                    <p>Your personal information will be handled according to our Privacy Policy.</p>
                    
                    <h6>5. Account Security</h6>
                    <p>You are responsible for maintaining the security of your account credentials.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Information Collection</h6>
                    <p>We collect information necessary for academic scheduling and system administration.</p>
                    
                    <h6>Data Usage</h6>
                    <p>Your data is used solely for timetable management and academic purposes.</p>
                    
                    <h6>Data Protection</h6>
                    <p>We implement appropriate security measures to protect your personal information.</p>
                    
                    <h6>Data Sharing</h6>
                    <p>Your information is not shared with third parties without your consent.</p>
                    
                    <h6>Contact</h6>
                    <p>For privacy concerns, contact us at <?php echo SYSTEM_EMAIL; ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password validation and strength check
        function validatePassword(password) {
            const rules = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };

            // Update rule indicators
            updateRuleIndicator('lengthRule', rules.length);
            updateRuleIndicator('uppercaseRule', rules.uppercase);
            updateRuleIndicator('lowercaseRule', rules.lowercase);
            updateRuleIndicator('numberRule', rules.number);
            updateRuleIndicator('specialRule', rules.special);

            // Calculate strength
            const strength = Object.values(rules).filter(Boolean).length;
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');
            
            strengthFill.className = 'strength-fill';
            switch(strength) {
                case 0:
                case 1:
                    strengthFill.classList.add('fill-weak');
                    strengthText.textContent = 'Password Strength: Too Weak';
                    strengthText.className = 'password-strength-text strength-weak';
                    break;
                case 2:
                case 3:
                    strengthFill.classList.add('fill-fair');
                    strengthText.textContent = 'Password Strength: Fair';
                    strengthText.className = 'password-strength-text strength-fair';
                    break;
                case 4:
                    strengthFill.classList.add('fill-good');
                    strengthText.textContent = 'Password Strength: Good';
                    strengthText.className = 'password-strength-text strength-good';
                    break;
                case 5:
                    strengthFill.classList.add('fill-strong');
                    strengthText.textContent = 'Password Strength: Strong';
                    strengthText.className = 'password-strength-text strength-strong';
                    break;
            }
            
            return strength >= 4; // Require at least "Good" strength
        }

        function updateRuleIndicator(ruleId, isValid) {
            const ruleElement = document.getElementById(ruleId);
            const icon = ruleElement.querySelector('i');
            
            if (isValid) {
                icon.className = 'fas fa-check-circle rule-valid';
                ruleElement.classList.add('rule-valid');
                ruleElement.classList.remove('rule-invalid');
            } else {
                icon.className = 'fas fa-circle rule-invalid';
                ruleElement.classList.add('rule-invalid');
                ruleElement.classList.remove('rule-valid');
            }
        }
        
        // Add password input event listener
        document.getElementById('password').addEventListener('input', function(e) {
            validatePassword(e.target.value);
        });
        
        // Form submission validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!validatePassword(password)) {
                e.preventDefault();
                alert('Please create a stronger password that meets all requirements.');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return;
            }
        });
        
        // Username suggestion
        function generateUsername() {
            const firstName = document.getElementById('first_name').value.toLowerCase();
            const lastName = document.getElementById('last_name').value.toLowerCase();
            
            if (firstName && lastName) {
                const usernameField = document.getElementById('username');
                if (!usernameField.value) {
                    usernameField.value = firstName + '.' + lastName;
                }
            }
        }
        
        document.getElementById('first_name').addEventListener('blur', generateUsername);
        document.getElementById('last_name').addEventListener('blur', generateUsername);
        
        // Form submission with loading state
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            
            // Validate form
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            
            // Add loading state
            submitBtn.classList.add('loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            submitBtn.disabled = true;
        });
        
        // Email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.setCustomValidity('Please enter a valid email address');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            }
        });
        
        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{3})/, '($1) $2');
            }
            this.value = value;
        });
        
        // Clear error messages on input
        document.querySelectorAll('.form-control').forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.style.display = 'none';
                }
            });
        });
        
        // Auto-focus first empty field
        window.addEventListener('load', function() {
            const firstEmptyField = document.querySelector('input:not([value]):not([type="hidden"]):not([type="checkbox"])');
            if (firstEmptyField) {
                firstEmptyField.focus();
            }
        });
        
        // Department change handling
        document.getElementById('department_id').addEventListener('change', function() {
            if (this.value === '') {
                // Could add custom department input here
                console.log('Other department selected');
            }
        });
        
        // Student number validation (if student)
        <?php if ($registrationType === 'student'): ?>
        document.getElementById('student_number').addEventListener('input', function() {
            // Remove non-alphanumeric characters
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        });
        <?php endif; ?>
        
        // Employee ID validation (if faculty)
        <?php if ($registrationType === 'faculty'): ?>
        document.getElementById('employee_id').addEventListener('input', function() {
            // Format employee ID
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
        });
        <?php endif; ?>
        
        // Form validation enhancement
        (function() {
            'use strict';
            
            // Add Bootstrap validation classes
            const forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Success message auto-redirect
        <?php if (!empty($success)): ?>
        setTimeout(function() {
            const loginBtn = document.querySelector('a[href="login.php"]');
            if (loginBtn) {
                loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Redirecting to Login...';
                setTimeout(function() {
                    window.location.href = 'login.php?msg=registration_success';
                }, 2000);
            }
        }, 5000);
        <?php endif; ?>
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit form
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('registrationForm').submit();
            }
        });
        
        // Updated password visibility toggle function
        function togglePasswordVisibility(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>