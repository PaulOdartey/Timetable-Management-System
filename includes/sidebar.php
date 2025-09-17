<?php
// Enhanced Sidebar Component - Timetable Management System
// Ensure user is logged in
if (!isset($_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userRole = $_SESSION['role'];
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Get the base URL for the application
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseDir = '/timetable-management'; // Adjust this to your actual base directory
$baseUrl = $protocol . '://' . $host . $baseDir;

// Function to generate correct URLs
function generateUrl($path, $baseUrl) {
    // Remove leading slash if present
    $path = ltrim($path, '/');
    return $baseUrl . '/' . $path;
}

// Define navigation menus for each role with corrected URLs
$navigationMenus = [
    'admin' => [
        [
            'title' => 'Dashboard',
            'icon' => '<path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'index.php',
            'badge' => null
        ],
        [
            'title' => 'User Management',
            'icon' => '<path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 6.79086 11 9 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89317 18.7122 8.75608 18.1676 9.45768C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'submenu' => [
                [
                    'title' => 'Pending Registrations',
                    'url' => 'admin/users/pending.php',
                    'badge_query' => "SELECT COUNT(*) as count FROM users WHERE status = 'pending'"
                ],
                [
                    'title' => 'All Users',
                    'url' => 'admin/users/index.php'
                ],
                [
                    'title' => 'Create User',
                    'url' => 'admin/users/create.php'
                ],
               
            ]
        ],
        [
            'title' => 'Academic Resources',
            'icon' => '<path d="M2 3H8C9.06087 3 10.0783 3.42143 10.8284 4.17157C11.5786 4.92172 12 5.93913 12 7V21C12 20.2044 11.6839 19.4413 11.1213 18.8787C10.5587 18.3161 9.79565 18 9 18H2V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 3H16C14.9391 3 13.9217 3.42143 13.1716 4.17157C12.4214 4.92172 12 5.93913 12 7V21C12 20.2044 12.3161 19.4413 12.8787 18.8787C13.4413 18.3161 14.2044 18 15 18H22V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'submenu' => [
                [
                    'title' => 'Subjects',
                    'url' => 'admin/subjects/index.php'
                ],
                [
                    'title' => 'Classrooms',
                    'url' => 'admin/classrooms/index.php'
                ],
                [
                    'title' => 'Time Slots',
                    'url' => 'admin/time-slots/index.php'
                ],
                [
                    'title' => 'Assign Faculty',
                    'url' => 'admin/subjects/assign-faculty.php'
                ],
                [
                    'title' => 'Student Enrollments',
                    'url' => 'admin/enrollments/index.php'
                ]
            ]
        ],
       [
            'title' => 'Departments',
            'icon' => '<path d="M19 21V5C19 3.89543 18.1046 3 17 3H7C5.89543 3 5 3.89543 5 5V21L12 17L19 21Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 7H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 11H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'submenu' => [
                [
                    'title' => 'All Departments',
                    'url' => 'admin/departments/index.php'
                ],
                [
                    'title' => 'Add Department',
                    'url' => 'admin/departments/create.php'
                ],
                [
                    'title' => 'Department Resources',
                    'url' => 'admin/departments/resources.php'
                ],
            ]
        ],
        [
            'title' => 'Timetable Management',
            'icon' => '<path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM19 19H5V8H19V19ZM19 6H5V5H19V6Z" fill="currentColor"/><path d="M7 10H9V12H7V10ZM11 10H13V12H11V10ZM15 10H17V12H15V10ZM7 14H9V16H7V14ZM11 14H13V16H11V14ZM15 14H17V16H15V14Z" fill="currentColor"/>' ,
            'submenu' => [

                [
                    'title' => 'Manage Schedules',
                    'url' => 'admin/timetable/index.php'
                ],
                [
                    'title' => 'Create Schedule',
                    'url' => 'admin/timetable/create.php'
                ],
              
                [
                    'title' => 'Schedule Overview',
                    'url' => 'admin/timetable/overview.php'
                ],
            ]
        ],
        [
            'title' => 'Reports Management',
            'icon' => '<path d="M9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17ZM19.5 19.1H4.5V5H6.5V3H19.5V19.1ZM19.5 1H3.5C2.95 1 2.5 1.45 2.5 2V20C2.5 20.55 2.95 21 3.5 21H20.5C21.05 21 21.5 20.55 21.5 20V2C21.5 1.45 21.05 1 20.5 1H19.5Z" fill="currentColor"/>',
            'url' => 'admin/reports/index.php'
           
        ],
        [
            'title' => 'Notifications',
            'icon' => '<path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'admin/notifications/index.php'
        ],
        [
            'title' => 'System Settings',
            'icon' => '<path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
              'url' => 'admin/settings/index.php'
            
            
        ]
    ],
    'faculty' => [
        [
            'title' => 'Dashboard',
            'icon' => '<path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/index.php'
        ],
        [
            'title' => 'My Schedule',
            'icon' => '<path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM19 19H5V8H19V19ZM19 6H5V5H19V6Z" fill="currentColor"/><path d="M7 10H9V12H7V10ZM11 10H13V12H11V10ZM15 10H17V12H15V10ZM7 14H9V16H7V14ZM11 14H13V16H11V14ZM15 14H17V16H15V14Z" fill="currentColor"/>',
            'url' => 'faculty/schedule.php'
        ],
        [
            'title' => 'My Subjects',
            'icon' => '<path d="M2 3H8C9.06087 3 10.0783 3.42143 10.8284 4.17157C11.5786 4.92172 12 5.93913 12 7V21C12 20.2044 11.6839 19.4413 11.1213 18.8787C10.5587 18.3161 9.79565 18 9 18H2V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 3H16C14.9391 3 13.9217 3.42143 13.1716 4.17157C12.4214 4.92172 12 5.93913 12 7V21C12 20.2044 12.3161 19.4413 12.8787 18.8787C13.4413 18.3161 14.2044 18 15 18H22V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/subjects.php'
        ],
        [
            'title' => 'Students',
            'icon' => '<path d="M17 21V19C17 17.9391 16.5786 16.9217 15.8284 16.1716C15.0783 15.4214 14.0609 15 13 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 11C11.2091 11 13 9.20914 13 7C13 4.79086 11.2091 3 9 3C6.79086 3 5 4.79086 5 7C5 9.20914 6.79086 11 9 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M23 21V19C22.9993 18.1137 22.7044 17.2528 22.1614 16.5523C21.6184 15.8519 20.8581 15.3516 20 15.13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 3.13C16.8604 3.35031 17.623 3.85071 18.1676 4.55232C18.7122 5.25392 19.0078 6.11683 19.0078 7.005C19.0078 7.89317 18.7122 8.75608 18.1676 9.45768C17.623 10.1593 16.8604 10.6597 16 10.88" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/students.php'
        ],
        [
            'title' => 'Notifications',
            'icon' => '<path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/notifications/index.php'
        ],
        [
            'title' => 'Export Schedule',
            'icon' => '<path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.96086 2.96086 5.46957 3.46957 6 4V20C6 20.5304 4.96086 21.0391 4.58579 21.4142C4.21071 21.7893 4 21.5304 4 21V8L14 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 2V8H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 13H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 17H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 9H9H8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/export.php'
        ],
        [
            'title' => 'Profile Settings',
            'icon' => '<path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M19.4 15C19.2669 15.3016 19.2272 15.6362 19.286 15.9606C19.3448 16.285 19.4995 16.5843 19.73 16.82L19.79 16.88C19.976 17.0657 20.1235 17.2863 20.2241 17.5291C20.3248 17.7719 20.3766 18.0322 20.3766 18.295C20.3766 18.5578 20.3248 18.8181 20.2241 19.0609C20.1235 19.3037 19.976 19.5243 19.79 19.71C19.6043 19.896 19.3837 20.0435 19.1409 20.1441C18.8981 20.2448 18.6378 20.2966 18.375 20.2966C18.1122 20.2966 17.8519 20.2448 17.6091 20.1441C17.3663 20.0435 17.1457 19.896 16.96 19.71L16.9 19.65C16.6643 19.4195 16.365 19.2648 16.0406 19.206C15.7162 19.1472 15.3816 19.1869 15.08 19.32" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'faculty/profile.php'
        ]
    ],
    'student' => [
        [
            'title' => 'Dashboard',
            'icon' => '<path d="M3 9L12 2L21 9V20C21 20.5304 20.7893 21.0391 20.4142 21.4142C20.0391 21.7893 19.5304 22 19 22H5C4.46957 22 3.96086 21.7893 3.58579 21.4142C3.21071 21.0391 3 20.5304 3 20V9Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 22V12H15V22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'student/index.php'
        ],
        [
            'title' => 'My Timetable',
            'icon' => '<path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM19 19H5V8H19V19ZM19 6H5V5H19V6Z" fill="currentColor"/><path d="M7 10H9V12H7V10ZM11 10H13V12H11V10ZM15 10H17V12H15V10ZM7 14H9V16H7V14ZM11 14H13V16H11V14ZM15 14H17V16H15V14Z" fill="currentColor"/>',
            'url' => 'student/timetable.php'
        ],
        [
            'title' => 'My Subjects',
            'icon' => '<path d="M2 3H8C9.06087 3 10.0783 3.42143 10.8284 4.17157C11.5786 4.92172 12 5.93913 12 7V21C12 20.2044 11.6839 19.4413 11.1213 18.8787C10.5587 18.3161 9.79565 18 9 18H2V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 3H16C14.9391 3 13.9217 3.42143 13.1716 4.17157C12.4214 4.92172 12 5.93913 12 7V21C12 20.2044 12.3161 19.4413 12.8787 18.8787C13.4413 18.3161 14.2044 18 15 18H22V3Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'student/subjects.php'
        ],
        [
            'title' => 'Academic Progress',
            'icon' => '<path d="M9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17ZM19.5 19.1H4.5V5H6.5V3H19.5V19.1ZM19.5 1H3.5C2.95 1 2.5 1.45 2.5 2V20C2.5 20.55 2.95 21 3.5 21H20.5C21.05 21 21.5 20.55 21.5 20V2C21.5 1.45 21.05 1 20.5 1H19.5Z" fill="currentColor"/>',
            'url' => 'student/progress.php'
        ],
        [
            'title' => 'Notifications',
            'icon' => '<path d="M18 8C18 6.4087 17.3679 4.88258 16.2426 3.75736C15.1174 2.63214 13.5913 2 12 2C10.4087 2 8.88258 2.63214 7.75736 3.75736C6.63214 4.88258 6 6.4087 6 8C6 15 3 17 3 17H21C21 17 18 15 18 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21C13.5542 21.3031 13.3019 21.5556 12.9988 21.7314C12.6956 21.9072 12.3522 21.999 12 21.999C11.6478 21.999 11.3044 21.9072 11.0012 21.7314C10.6981 21.5556 10.4458 21.3031 10.27 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'student/notifications/index.php'
        ],
        [
            'title' => 'Export Schedule',
            'icon' => '<path d="M6 9V2C6 1.46957 6.21071 0.960859 6.58579 0.585786C6.96086 0.210714 7.46957 0 8 0L16 0C16.5304 0 17.0391 0.210714 17.4142 0.585786C17.7893 0.960859 18 1.46957 18 2V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 18H4C3.46957 18 2.96086 17.7893 2.58579 17.4142C2.21071 17.0391 2 16.5304 2 16V11C2 10.4696 2.21071 9.96086 2.58579 9.58579C2.96086 9.21071 3.46957 9 4 9H20C20.5304 9 21.0391 9.21071 21.4142 9.58579C21.7893 9.96086 22 10.4696 22 11V16C22 16.5304 21.7893 17.0391 21.4142 17.4142C21.0391 17.7893 20.5304 18 20 18H18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 14H6V22H18V14Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'student/export.php'
        ],
        [
            'title' => 'Profile',
            'icon' => '<path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
            'url' => 'student/profile.php'
        ]
    ]
];

// Function to check if current page is active
function isActiveMenu($url, $currentPage, $currentDir, $baseUrl) {
    // Convert the menu URL to absolute URL for comparison
    $absoluteUrl = generateUrl($url, $baseUrl);
    $currentFullUrl = $baseUrl . '/' . $currentDir . '/' . $currentPage;
    
    // Remove query string and fragment for comparison
    $absoluteUrl = strtok($absoluteUrl, '?');
    $currentFullUrl = strtok($currentFullUrl, '?');
    
    return $absoluteUrl === $currentFullUrl;
}

// Function to check if submenu has active item
function hasActiveSubmenu($submenu, $currentPage, $currentDir, $baseUrl) {
    foreach ($submenu as $item) {
        if (isActiveMenu($item['url'], $currentPage, $currentDir, $baseUrl)) {
            return true;
        }
    }
    return false;
}

// Function to get badge count
function getBadgeCount($badgeQuery) {
    if (empty($badgeQuery)) return 0;
    
    try {
        $db = Database::getInstance();
        $result = $db->fetchRow($badgeQuery);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

$currentMenus = $navigationMenus[$userRole] ?? [];
?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Enhanced Main Sidebar -->
<aside class="tms-sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM19 19H5V8H19V19ZM19 6H5V5H19V6Z" fill="currentColor"/>
                    <path d="M7 10H9V12H7V10ZM11 10H13V12H11V10ZM15 10H17V12H15V10ZM7 14H9V16H7V14ZM11 14H13V16H11V14ZM15 14H17V16H15V14Z" fill="currentColor"/>
                </svg>
            </div>
            <div class="brand-text">
                <span class="brand-title">Timetable Management</span>
                <span class="brand-subtitle"><?= ucfirst($userRole) ?> Panel</span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($currentMenus as $menu): ?>
                <?php
                $hasSubmenu = isset($menu['submenu']);
                $isActive = false;
                $isExpanded = false;
                
                if ($hasSubmenu) {
                    $isActive = hasActiveSubmenu($menu['submenu'], $currentPage, $currentDir, $baseUrl);
                    $isExpanded = $isActive;
                } else {
                    $isActive = isActiveMenu($menu['url'], $currentPage, $currentDir, $baseUrl);
                }
                
                $badgeCount = 0;
                if (isset($menu['badge_query'])) {
                    $badgeCount = getBadgeCount($menu['badge_query']);
                }
                ?>
                
                <li class="nav-item <?= $hasSubmenu ? 'has-submenu' : '' ?> <?= $isActive ? 'active' : '' ?> <?= $isExpanded ? 'expanded' : '' ?>">
                    <?php if ($hasSubmenu): ?>
                        <button class="nav-link submenu-toggle" onclick="toggleSubmenu(this)">
                            <div class="nav-link-content">
                                <div class="nav-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <?= $menu['icon'] ?>
                                    </svg>
                                </div>
                                <span class="nav-text"><?= htmlspecialchars($menu['title']) ?></span>
                            </div>
                            <div class="nav-actions">
                                <?php if ($badgeCount > 0): ?>
                                    <span class="nav-badge"><?= $badgeCount > 99 ? '99+' : $badgeCount ?></span>
                                <?php endif; ?>
                                <svg class="submenu-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                        </button>
                        
                        <!-- Submenu -->
                        <ul class="submenu">
                            <?php foreach ($menu['submenu'] as $submenuItem): ?>
                                <?php
                                $isSubActive = isActiveMenu($submenuItem['url'], $currentPage, $currentDir, $baseUrl);
                                $subBadgeCount = 0;
                                if (isset($submenuItem['badge_query'])) {
                                    $subBadgeCount = getBadgeCount($submenuItem['badge_query']);
                                }
                                ?>
                                <li class="submenu-item <?= $isSubActive ? 'active' : '' ?>">
                                    <a href="<?= generateUrl($submenuItem['url'], $baseUrl) ?>" class="submenu-link">
                                        <span class="submenu-text"><?= htmlspecialchars($submenuItem['title']) ?></span>
                                        <?php if ($subBadgeCount > 0): ?>
                                            <span class="nav-badge"><?= $subBadgeCount > 99 ? '99+' : $subBadgeCount ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <a href="<?= generateUrl($menu['url'], $baseUrl) ?>" class="nav-link">
                            <div class="nav-link-content">
                                <div class="nav-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <?= $menu['icon'] ?>
                                    </svg>
                                </div>
                                <span class="nav-text"><?= htmlspecialchars($menu['title']) ?></span>
                            </div>
                            <?php if ($badgeCount > 0): ?>
                                <div class="nav-actions">
                                    <span class="nav-badge"><?= $badgeCount > 99 ? '99+' : $badgeCount ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-collapse-btn">
            <button class="collapse-toggle" onclick="toggleSidebarCollapse()" title="Collapse Sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11 19L4 12L11 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        
        <div class="sidebar-version">
            <small>TMS v1.0</small>
        </div>
    </div>
</aside>

<!-- Enhanced Sidebar Styles -->
<style>
/* ============================================
   ENHANCED SIDEBAR STYLES WITH IMPROVEMENTS
   ============================================ */

/* Sidebar Layout */
.tms-sidebar {
    position: fixed;
    left: 0;
    top: 64px;
    bottom: 0;
    width: 280px;
    background: var(--bg-secondary); /* CHANGED: Enhanced background */
    border-right: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    z-index: 999;
    overflow: hidden;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05); /* ADDED: Subtle shadow */
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 998;
    opacity: 0;
    transition: opacity 0.3s ease;
    backdrop-filter: blur(2px); /* ADDED: Backdrop blur effect */
}

.sidebar-overlay.show {
    opacity: 1;
    backdrop-filter: blur(4px);
}

/* Enhanced Sidebar Header */
.sidebar-header {
    padding: 1.5rem 1.5rem 1rem;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
    background: var(--bg-primary); /* ADDED: Different background for header */
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.sidebar-header .brand-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2); /* ADDED: Enhanced shadow */
    transition: all 0.3s ease;
}

.sidebar-header .brand-icon:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.sidebar-header .brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    min-width: 0;
}

.sidebar-header .brand-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
}

.sidebar-header .brand-subtitle {
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: 500;
    text-transform: capitalize;
}

/* Enhanced Navigation */
.sidebar-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem 0;
    background: transparent; /* Uses sidebar's bg-secondary */
}

.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: var(--text-tertiary);
}

.nav-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-item {
    margin: 0.25rem 0;
}

/* Enhanced Nav Links */
.nav-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.875rem 1.5rem; /* CHANGED: Slightly more padding */
    color: var(--text-secondary);
    text-decoration: none;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); /* CHANGED: Better easing */
    border: none;
    background: none;
    width: 100%;
    cursor: pointer;
    border-radius: 0;
    position: relative;
}

.nav-link:hover {
    color: var(--text-primary);
    background: var(--bg-tertiary); /* CHANGED: Better hover background */
    transform: translateX(4px); /* ADDED: Subtle slide effect */
}

.nav-link-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1;
}

.nav-icon {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    transition: transform 0.2s ease; /* ADDED: Icon animation */
}

.nav-link:hover .nav-icon {
    transform: scale(1.1);
}

.nav-text {
    font-size: 0.875rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.nav-badge {
    background: var(--primary-color);
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.125rem 0.375rem;
    border-radius: 10px;
    min-width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    animation: pulse 2s infinite; /* ADDED: Subtle pulse animation */
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* Enhanced Active States */
.nav-item.active > .nav-link {
    color: var(--primary-color);
    background: linear-gradient(90deg, var(--primary-color-alpha) 0%, transparent 100%); /* ADDED: Gradient background */
    position: relative;
    font-weight: 600; /* ADDED: Bolder font weight */
}

.nav-item.active > .nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--primary-color);
    border-radius: 0 2px 2px 0; /* ADDED: Rounded indicator */
}

/* Enhanced Submenu Styles */
.has-submenu .submenu-toggle {
    position: relative;
}

.submenu-arrow {
    transition: transform 0.3s ease; /* CHANGED: Smoother transition */
    color: var(--text-tertiary);
}

.nav-item.expanded .submenu-arrow {
    transform: rotate(90deg);
    color: var(--primary-color); /* ADDED: Color change on expand */
}

.submenu {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    background: var(--bg-tertiary); /* CHANGED: Enhanced submenu background */
    border-radius: 0 0 8px 8px; /* ADDED: Rounded bottom corners */
}

.nav-item.expanded .submenu {
    max-height: 500px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05); /* ADDED: Inner shadow */
}

.submenu-item {
    margin: 0;
}

.submenu-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1.5rem 0.75rem 4rem; /* CHANGED: Better indentation */
    color: var(--text-secondary);
    text-decoration: none;
    font-size: 0.8125rem;
    transition: all 0.2s ease;
    position: relative;
    border-radius: 0 8px 8px 0; /* ADDED: Rounded right corners */
}

.submenu-link:hover {
    color: var(--text-primary);
    background: var(--bg-primary);
    transform: translateX(8px); /* ADDED: Slide effect */
}

.submenu-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.submenu-item.active .submenu-link {
    color: var(--primary-color);
    background: var(--primary-color-alpha);
    font-weight: 600;
    border-left: 2px solid var(--primary-color); /* ADDED: Left border indicator */
}

.submenu-item.active .submenu-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--primary-color);
}

/* Enhanced Sidebar Footer */
.sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    background: var(--bg-primary); /* ADDED: Match header background */
}

.collapse-toggle {
    width: 36px;
    height: 36px;
    border: none;
    background: var(--bg-secondary);
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    transition: all 0.3s ease; /* CHANGED: Better transition */
}

.collapse-toggle:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
    transform: scale(1.05); /* ADDED: Hover scale effect */
}

.sidebar-version {
    color: var(--text-tertiary);
    font-size: 0.75rem;
    font-weight: 500; /* ADDED: Better font weight */
}

/* Enhanced Collapsed Sidebar */
.tms-sidebar.collapsed {
    width: 70px;
}

.tms-sidebar.collapsed .brand-text,
.tms-sidebar.collapsed .nav-text,
.tms-sidebar.collapsed .nav-badge,
.tms-sidebar.collapsed .submenu-arrow,
.tms-sidebar.collapsed .sidebar-version {
    opacity: 0;
    visibility: hidden;
}

.tms-sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 0.875rem;
}

.tms-sidebar.collapsed .nav-link-content {
    justify-content: center;
}

.tms-sidebar.collapsed .submenu {
    display: none;
}

.tms-sidebar.collapsed .collapse-toggle svg {
    transform: rotate(180deg);
}

/* Enhanced Responsive Design */
@media (max-width: 1024px) {
    .tms-sidebar {
        transform: translateX(-100%);
        box-shadow: none; /* Remove shadow on mobile */
    }
    
    .tms-sidebar.mobile-open {
        transform: translateX(0);
        box-shadow: 2px 0 20px rgba(0, 0, 0, 0.15); /* Enhanced mobile shadow */
    }
    
    .sidebar-overlay {
        display: block;
    }
    
    .sidebar-overlay.show {
        display: block;
    }
}

@media (max-width: 768px) {
    .tms-sidebar {
        width: 100%;
        max-width: 320px;
    }
    
    .sidebar-header .brand-icon {
        width: 32px;
        height: 32px;
    }
    
    .sidebar-header .brand-icon svg {
        width: 24px;
        height: 24px;
    }
    
    .sidebar-header .brand-title {
        font-size: 1.1rem;
    }
    
    .sidebar-header .brand-subtitle {
        font-size: 0.7rem;
    }
}

/* Mobile and Tablet Responsive Styles */
@media (max-width: 1024px) {
    .tms-sidebar {
        position: fixed;
        left: -280px;
        top: 0;
        height: 100vh;
        z-index: 1001;
        transition: left 0.3s ease;
    }
    
    .tms-sidebar.mobile-open {
        left: 0;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    body.sidebar-open {
        overflow: hidden;
    }
    
    /* Adjust main content for mobile */
    .main-content {
        margin-left: 0 !important;
        padding-left: 0 !important;
    }
}

@media (max-width: 768px) {
    .tms-sidebar {
        width: 260px;
        left: -260px;
    }
    
    .nav-link {
        padding: 0.75rem 1rem;
    }
    
    .nav-icon {
        width: 18px;
        height: 18px;
    }
    
    .nav-text {
        font-size: 0.9rem;
    }
}

/* Dark mode enhancements */
[data-theme="dark"] .tms-sidebar {
    background: var(--bg-secondary);
    border-right-color: var(--border-color);
    box-shadow: 2px 0 20px rgba(0, 0, 0, 0.3); /* Enhanced dark mode shadow */
}

[data-theme="dark"] .sidebar-header {
    background: var(--bg-primary);
}

[data-theme="dark"] .sidebar-footer {
    background: var(--bg-primary);
}

[data-theme="dark"] .nav-link:hover {
    background: var(--bg-tertiary);
}

[data-theme="dark"] .nav-item.active > .nav-link {
    background: linear-gradient(90deg, var(--primary-color-alpha) 0%, transparent 100%);
    color: var(--primary-color);
}

[data-theme="dark"] .submenu {
    background: var(--bg-tertiary);
}

[data-theme="dark"] .submenu-link:hover {
    background: var(--bg-primary);
}

[data-theme="dark"] .sidebar-overlay.show {
    backdrop-filter: blur(8px); /* Enhanced dark mode blur */
}

/* Loading state for badges */
.nav-badge.loading {
    animation: pulse 1.5s infinite;
    background: var(--text-tertiary);
}

/* Focus states for accessibility */
.nav-link:focus,
.submenu-link:focus,
.collapse-toggle:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Smooth section transitions */
.nav-item + .nav-item {
    position: relative;
}

.nav-item:not(.has-submenu) + .nav-item:not(.has-submenu)::before {
    content: '';
    position: absolute;
    top: 0;
    left: 1.5rem;
    right: 1.5rem;
    height: 1px;
    background: var(--border-color);
    opacity: 0.3;
}

/* Enhanced hover effects */
.nav-link::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    background: var(--primary-color);
    transform: scaleX(0);
    transition: transform 0.3s ease;
    transform-origin: left;
}

.nav-item.active > .nav-link::after {
    transform: scaleX(1);
}
</style>

<!-- Enhanced Sidebar JavaScript -->
<script>
// Enhanced submenu toggle functionality
function toggleSubmenu(button) {
    const navItem = button.closest('.nav-item');
    const isExpanded = navItem.classList.contains('expanded');
    const sidebar = document.querySelector('.tms-sidebar');

    // If sidebar is collapsed, expand it first so submenu can be interacted with
    if (sidebar && sidebar.classList.contains('collapsed')) {
        // Expand the sidebar before toggling submenu
        toggleSidebarCollapse();
    }
    
    // Close all other submenus with animation
    document.querySelectorAll('.nav-item.expanded').forEach(item => {
        if (item !== navItem) {
            item.classList.remove('expanded');
            // Add closing animation class
            item.classList.add('closing');
            setTimeout(() => {
                item.classList.remove('closing');
            }, 300);
        }
    });
    
    // Toggle current submenu
    navItem.classList.toggle('expanded');
    
    // Add opening animation
    if (navItem.classList.contains('expanded')) {
        navItem.classList.add('opening');
        setTimeout(() => {
            navItem.classList.remove('opening');
        }, 300);
    }
    
    // Save expanded state
    const menuTitle = button.querySelector('.nav-text').textContent;
    const expandedMenus = getExpandedMenus();
    
    if (navItem.classList.contains('expanded')) {
        expandedMenus.push(menuTitle);
    } else {
        const index = expandedMenus.indexOf(menuTitle);
        if (index > -1) {
            expandedMenus.splice(index, 1);
        }
    }
    
    localStorage.setItem('expandedMenus', JSON.stringify(expandedMenus));
}

// Enhanced sidebar collapse functionality
function toggleSidebarCollapse() {
    const sidebar = document.querySelector('.tms-sidebar');
    const isCollapsed = sidebar.classList.contains('collapsed');
    
    sidebar.classList.toggle('collapsed');
    
    // Add transition classes for smooth animation
    sidebar.classList.add('transitioning');
    setTimeout(() => {
        sidebar.classList.remove('transitioning');
    }, 300);
    
    // Save collapsed state
    localStorage.setItem('sidebarCollapsed', !isCollapsed);
    
    // Adjust main content margin
    adjustMainContentMargin();
    
    // Dispatch custom event for other components
    window.dispatchEvent(new CustomEvent('sidebarToggled', { 
        detail: { collapsed: !isCollapsed } 
    }));
}

// Get expanded menus from localStorage
function getExpandedMenus() {
    try {
        return JSON.parse(localStorage.getItem('expandedMenus') || '[]');
    } catch (e) {
        return [];
    }
}

// Enhanced restore sidebar state
function restoreSidebarState() {
    const sidebar = document.querySelector('.tms-sidebar');
    
    // Restore collapsed state
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('collapsed');
    }
    
    // Restore expanded menus with animation
    const expandedMenus = getExpandedMenus();
    expandedMenus.forEach(menuTitle => {
        const menuButton = Array.from(document.querySelectorAll('.nav-text'))
            .find(el => el.textContent === menuTitle);
        
        if (menuButton) {
            const navItem = menuButton.closest('.nav-item');
            navItem.classList.add('expanded');
        }
    });
    
    adjustMainContentMargin();
}

// Enhanced adjust main content margin
function adjustMainContentMargin() {
    const sidebar = document.querySelector('.tms-sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (mainContent && window.innerWidth > 1024) {
        const sidebarWidth = sidebar.classList.contains('collapsed') ? 70 : 280;
        mainContent.style.marginLeft = sidebarWidth + 'px';
        mainContent.style.transition = 'margin-left 0.3s ease';
    }
}

// Enhanced window resize handler
function handleWindowResize() {
    if (window.innerWidth <= 1024) {
        // Mobile: remove collapsed class and margin
        const sidebar = document.querySelector('.tms-sidebar');
        const mainContent = document.querySelector('.main-content');
        
        sidebar.classList.remove('collapsed');
        if (mainContent) {
            mainContent.style.marginLeft = '0';
        }
    } else {
        // Desktop: restore state and adjust margin
        restoreSidebarState();
    }
}

// Enhanced navigation badges update
async function updateNavigationBadges() {
    try {
        showBadgeLoading();
        
        // Simulated API call - replace with actual API
        const response = await fetch('/timetable-management/includes/api/navigation-badges.php');
        const data = await response.json();
        
        if (data.success) {
            // Update badges with animation
            Object.entries(data.badges).forEach(([key, count]) => {
                const badge = document.querySelector(`[data-badge="${key}"]`);
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'flex';
                        badge.classList.add('updated');
                        setTimeout(() => badge.classList.remove('updated'), 500);
                    } else {
                        badge.style.display = 'none';
                    }
                }
            });
        }
        
        hideBadgeLoading();
    } catch (error) {
        console.error('Failed to update navigation badges:', error);
        hideBadgeLoading();
    }
}

// Enhanced badge loading states
function showBadgeLoading() {
    document.querySelectorAll('.nav-badge').forEach(badge => {
        badge.classList.add('loading');
    });
}

function hideBadgeLoading() {
    document.querySelectorAll('.nav-badge').forEach(badge => {
        badge.classList.remove('loading');
    });
}

// Enhanced current page highlighting
function highlightCurrentPage() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link[href], .submenu-link[href]');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.includes(href)) {
            const navItem = link.closest('.nav-item');
            const submenuItem = link.closest('.submenu-item');
            
            if (submenuItem) {
                submenuItem.classList.add('active');
                // Expand parent menu with animation
                const parentNavItem = submenuItem.closest('.nav-item.has-submenu');
                if (parentNavItem) {
                    parentNavItem.classList.add('expanded', 'active');
                }
            } else {
                navItem.classList.add('active');
            }
        }
    });
}

// Enhanced initialization
document.addEventListener('DOMContentLoaded', function() {
    // Restore sidebar state
    restoreSidebarState();
    
    // Highlight current page
    highlightCurrentPage();
    
    // Handle window resize
    window.addEventListener('resize', handleWindowResize);
    
    // Update badges initially and periodically
    updateNavigationBadges();
    setInterval(updateNavigationBadges, 30000);
    
    // Enhanced hover effects
    const navLinks = document.querySelectorAll('.nav-link, .submenu-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(4px)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });
    
    // Enhanced keyboard navigation support
    document.addEventListener('keydown', function(e) {
        // Alt + S to toggle sidebar collapse
        if (e.altKey && e.key === 's') {
            e.preventDefault();
            toggleSidebarCollapse();
        }
        
        // Escape to close mobile sidebar
        if (e.key === 'Escape') {
            const sidebar = document.querySelector('.tms-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar.classList.contains('mobile-open')) {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        }
    });
    
    // Enhanced touch support for mobile
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipeGesture();
    });
    
    function handleSwipeGesture() {
        const swipeThreshold = 80; // Increased threshold
        const sidebar = document.querySelector('.tms-sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        // Swipe right to open sidebar
        if (touchEndX - touchStartX > swipeThreshold && touchStartX < 50) {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('show');
            document.body.classList.add('sidebar-open');
        }
        
        // Swipe left to close sidebar  
        if (touchStartX - touchEndX > swipeThreshold && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('show');
            document.body.classList.remove('sidebar-open');
        }
    }
    
    // Enhanced performance monitoring
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.nav-item').forEach(item => {
        observer.observe(item);
    });
});

// Enhanced global sidebar toggle function
window.toggleSidebar = function() {
    const sidebar = document.querySelector('.tms-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
    body.classList.toggle('sidebar-open');
    
    // Add haptic feedback on supported devices
    if (navigator.vibrate) {
        navigator.vibrate(50);
    }
};

// Enhanced close sidebar on link click (mobile)
document.addEventListener('click', function(e) {
    if (e.target.matches('.submenu-link')) {
        // Close mobile sidebar when clicking submenu links
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.tms-sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            setTimeout(() => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }, 150);
        }
    }
});

// Add CSS animation classes
const animationStyles = `
<style>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.nav-item.visible {
    animation: fadeInUp 0.3s ease forwards;
}

.nav-badge.updated {
    animation: bounce 0.5s ease;
}

@keyframes bounce {
    0%, 20%, 60%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    80% {
        transform: translateY(-5px);
    }
}

.tms-sidebar.transitioning {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.nav-item.opening .submenu {
    animation: slideDown 0.3s ease;
}

.nav-item.closing .submenu {
    animation: slideUp 0.3s ease;
}

@keyframes slideDown {
    from {
        max-height: 0;
        opacity: 0;
    }
    to {
        max-height: 500px;
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        max-height: 500px;
        opacity: 1;
    }
    to {
        max-height: 0;
        opacity: 0;
    }
}
</style>
`;

document.head.insertAdjacentHTML('beforeend', animationStyles);
</script>