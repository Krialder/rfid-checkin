<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$db = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help & Support - Electronic Check-in System</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/help.css">
</head>
<body>
    <?php include '../includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="help-header">
            <div class="container">
                <h1>Help & Support</h1>
                <p>Everything you need to know about the Electronic Check-in System</p>
            
            <div class="help-search">
                <input type="text" id="searchInput" placeholder="Search for help topics, features, or issues...">
                <button onclick="searchHelp()">Search</button>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Quick Navigation -->
        <div class="help-nav">
            <div class="help-nav-grid">
                <a href="#getting-started" class="help-nav-item">
                    <span class="help-nav-icon">🚀</span>
                    <div>
                        <h4>Getting Started</h4>
                        <p>Learn the basics</p>
                    </div>
                </a>
                <a href="#user-guide" class="help-nav-item">
                    <span class="help-nav-icon">👤</span>
                    <div>
                        <h4>User Guide</h4>
                        <p>How to use features</p>
                    </div>
                </a>
                <a href="#hardware-setup" class="help-nav-item">
                    <span class="help-nav-icon">🔧</span>
                    <div>
                        <h4>Hardware Setup</h4>
                        <p>RFID configuration</p>
                    </div>
                </a>
                <a href="#troubleshooting" class="help-nav-item">
                    <span class="help-nav-icon">🔍</span>
                    <div>
                        <h4>Troubleshooting</h4>
                        <p>Fix common issues</p>
                    </div>
                </a>
                <a href="#faq" class="help-nav-item">
                    <span class="help-nav-icon">❓</span>
                    <div>
                        <h4>FAQ</h4>
                        <p>Frequently asked questions</p>
                    </div>
                </a>
                <a href="#contact" class="help-nav-item">
                    <span class="help-nav-icon">📞</span>
                    <div>
                        <h4>Contact Support</h4>
                        <p>Get personal help</p>
                    </div>
                </a>
            </div>
        </div>
        
        <div class="help-content">
            <div class="help-main">
                <!-- Getting Started Section -->
                <div class="help-section" id="getting-started">
                    <h2>🚀 Getting Started</h2>
                    <p>Welcome to the Electronic Check-in System! This guide will help you get started quickly.</p>
                    
                    <h3>First Steps</h3>
                    <ol class="step-list">
                        <li><strong>Login:</strong> Use your username and password to access the system at the login page.</li>
                        <li><strong>Dashboard:</strong> Once logged in, you'll see your personalized dashboard with recent activity and quick actions.</li>
                        <li><strong>Profile Setup:</strong> Visit your profile page to add your photo, update contact information, and manage RFID tags.</li>
                        <li><strong>First Check-in:</strong> Try checking into an event using the dashboard or scan your RFID card at a reader.</li>
                    </ol>
                    
                    <h3>System Overview</h3>
                    <div class="feature-grid">
                        <div class="feature-card">
                            <h4>Dashboard</h4>
                            <p>Your central hub showing statistics, recent activity, and quick actions for checking into events.</p>
                        </div>
                        <div class="feature-card">
                            <h4>Events</h4>
                            <p>Browse upcoming events, view details, and quickly check in to participate.</p>
                        </div>
                        <div class="feature-card">
                            <h4>My Check-ins</h4>
                            <p>View your personal attendance history, export records, and track your participation.</p>
                        </div>
                        <div class="feature-card">
                            <h4>Analytics</h4>
                            <p>See detailed statistics about your attendance patterns and system-wide activity.</p>
                        </div>
                        <div class="feature-card">
                            <h4>Profile</h4>
                            <p>Manage your account information, upload avatar, and configure RFID tags.</p>
                        </div>
                        <div class="feature-card">
                            <h4>Settings</h4>
                            <p>Customize your experience, manage security settings, and control notifications.</p>
                        </div>
                    </div>
                </div>
                
                <!-- User Guide Section -->
                <div class="help-section" id="user-guide">
                    <h2>👤 User Guide</h2>
                    
                    <h3>How to Check In to Events</h3>
                    <h4>Method 1: RFID Card</h4>
                    <ol class="step-list">
                        <li>Locate an RFID reader device (usually near event entrances)</li>
                        <li>Hold your RFID card near the reader</li>
                        <li>Wait for the green light and confirmation beep</li>
                        <li>Your check-in is automatically recorded</li>
                    </ol>
                    
                    <h4>Method 2: Manual Check-in via Dashboard</h4>
                    <ol class="step-list">
                        <li>Login to the system and go to your dashboard</li>
                        <li>Click the "Manual Check-in" button</li>
                        <li>Select the event you want to check into</li>
                        <li>Click "Check In" to confirm</li>
                    </ol>
                    
                    <h4>Method 3: Quick Check-in from Events Page</h4>
                    <ol class="step-list">
                        <li>Go to the "Events" page</li>
                        <li>Browse or search for the event</li>
                        <li>Click "Quick Check-in" on the event card</li>
                        <li>Confirm your check-in in the popup</li>
                    </ol>
                    
                    <h3>Managing Your Profile</h3>
                    <p>Your profile contains important information and settings:</p>
                    
                    <h4>Profile Tab</h4>
                    <ul>
                        <li><strong>Avatar:</strong> Upload a profile picture (JPEG, PNG, up to 2MB)</li>
                        <li><strong>Contact Information:</strong> Update email, phone, department</li>
                        <li><strong>Bio:</strong> Add a personal description</li>
                    </ul>
                    
                    <h4>RFID Tab</h4>
                    <ul>
                        <li><strong>Assigned Tags:</strong> View your current RFID cards</li>
                        <li><strong>Tag Status:</strong> See which tags are active</li>
                        <li><strong>Request New Tag:</strong> Contact admin for additional cards</li>
                    </ul>
                    
                    <h4>Security Tab</h4>
                    <ul>
                        <li><strong>Change Password:</strong> Update your login credentials</li>
                        <li><strong>Two-Factor Auth:</strong> Enable additional security (if available)</li>
                        <li><strong>Login History:</strong> Review recent access to your account</li>
                    </ul>
                </div>
                
                <!-- Hardware Setup Section -->
                <div class="help-section" id="hardware-setup">
                    <h2>🔧 Hardware Setup</h2>
                    <p>This section is for administrators setting up RFID readers.</p>
                    
                    <h3>RFID Reader Configuration</h3>
                    <ol class="step-list">
                        <li><strong>Hardware Assembly:</strong> Connect RC522 module to NodeMCU according to wiring diagram</li>
                        <li><strong>Software Upload:</strong> Flash the appropriate Arduino code to your device</li>
                        <li><strong>WiFi Configuration:</strong> Connect to "RFID-Setup" network and configure WiFi settings</li>
                        <li><strong>Server Integration:</strong> Set the server URL to point to your installation</li>
                        <li><strong>Testing:</strong> Verify RFID scanning works and data reaches the server</li>
                    </ol>
                    
                    <h3>Hardware Troubleshooting</h3>
                    <table class="troubleshooting-table">
                        <thead>
                            <tr>
                                <th>Problem</th>
                                <th>Possible Causes</th>
                                <th>Solutions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>RFID not reading cards</td>
                                <td>Power issues, wiring, card compatibility</td>
                                <td>Check 3.3V power supply, verify wiring, test with different cards</td>
                            </tr>
                            <tr>
                                <td>WiFi connection failed</td>
                                <td>Wrong credentials, weak signal</td>
                                <td>Recheck SSID/password, move closer to router</td>
                            </tr>
                            <tr>
                                <td>Server communication error</td>
                                <td>Wrong URL, network issues</td>
                                <td>Verify server URL, check network connectivity</td>
                            </tr>
                            <tr>
                                <td>Device keeps rebooting</td>
                                <td>Power supply inadequate</td>
                                <td>Use quality 5V 2A power supply, check connections</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Troubleshooting Section -->
                <div class="help-section" id="troubleshooting">
                    <h2>🔍 Troubleshooting</h2>
                    
                    <h3>Common Issues</h3>
                    
                    <h4>Login Problems</h4>
                    <ul>
                        <li><strong>Forgot password:</strong> Use the "Forgot Password" link on the login page</li>
                        <li><strong>Account locked:</strong> Contact administrator after multiple failed attempts</li>
                        <li><strong>Username not recognized:</strong> Check spelling or contact admin</li>
                    </ul>
                    
                    <h4>Check-in Issues</h4>
                    <ul>
                        <li><strong>RFID card not working:</strong> Ensure card is assigned to your account</li>
                        <li><strong>Event not showing:</strong> Check if event is public and currently active</li>
                        <li><strong>Already checked in error:</strong> You may already be checked into this event</li>
                    </ul>
                    
                    <h4>Performance Issues</h4>
                    <ul>
                        <li><strong>Slow loading:</strong> Check internet connection, try refreshing page</li>
                        <li><strong>Pages not updating:</strong> Clear browser cache and cookies</li>
                        <li><strong>Mobile issues:</strong> Ensure you're using a supported browser</li>
                    </ul>
                    
                    <h3>Browser Support</h3>
                    <p>The system works best with modern browsers:</p>
                    <ul>
                        <li>✅ Chrome 90+</li>
                        <li>✅ Firefox 88+</li>
                        <li>✅ Safari 14+</li>
                        <li>✅ Edge 90+</li>
                        <li>❌ Internet Explorer (not supported)</li>
                    </ul>
                </div>
                
                <!-- FAQ Section -->
                <div class="help-section" id="faq">
                    <h2>❓ Frequently Asked Questions</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I get an RFID card?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Contact your system administrator to request an RFID card. They will need to assign the card to your account and provide you with the physical card.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Can I check into multiple events at once?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Yes, you can be checked into multiple events simultaneously. However, some events may have restrictions based on location or time conflicts.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I check out of an event?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Checkout is typically automatic when an event ends. For manual checkout, scan your RFID card again or use the dashboard check-out option if available.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Can I see who else is attending an event?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Attendee lists may be visible depending on event privacy settings and your permissions. Check the event details page for attendee information.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How can I export my attendance history?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Go to the "My Check-ins" page and use the export button to download your attendance history as a CSV file.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>What should I do if I lost my RFID card?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Contact your administrator immediately to deactivate the lost card and request a replacement. You can still use manual check-in while waiting for a new card.
                        </div>
                    </div>
                </div>
                
                <!-- Contact Section -->
                <div class="help-section" id="contact">
                    <h2>📞 Contact Support</h2>
                    <p>Need additional help? Our support team is here to assist you.</p>
                    
                    <div class="contact-form">
                        <h3>Send us a message</h3>
                        <form id="contactForm">
                            <div class="form-group">
                                <label for="contact_name">Your Name</label>
                                <input type="text" id="contact_name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_email">Email Address</label>
                                <input type="email" id="contact_email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_category">Category</label>
                                <select id="contact_category" name="category">
                                    <option value="general">General Question</option>
                                    <option value="technical">Technical Issue</option>
                                    <option value="account">Account Problem</option>
                                    <option value="hardware">Hardware Issue</option>
                                    <option value="feature">Feature Request</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="contact_message">Message</label>
                                <textarea id="contact_message" name="message" rows="5" required placeholder="Please describe your issue or question in detail..."></textarea>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Keyboard Shortcuts -->
                <div class="help-section" id="shortcuts">
                    <h2>⌨️ Keyboard Shortcuts</h2>
                    <p>Speed up your workflow with these keyboard shortcuts:</p>
                    
                    <table class="troubleshooting-table">
                        <thead>
                            <tr>
                                <th>Shortcut</th>
                                <th>Action</th>
                                <th>Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="keyboard-shortcut">Ctrl + /</span></td>
                                <td>Show help</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + D</span></td>
                                <td>Go to Dashboard</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + E</span></td>
                                <td>Go to Events</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + P</span></td>
                                <td>Go to Profile</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Ctrl + K</span></td>
                                <td>Quick search</td>
                                <td>Events page</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Esc</span></td>
                                <td>Close modal</td>
                                <td>All modals</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="help-sidebar">
                <div class="sidebar-card">
                    <h3>System Status</h3>
                    <div class="status-row">
                        <span class="status-indicator status-online">Online</span>
                        <span>All systems operational</span>
                    </div>
                    <p>Last updated: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                
                <div class="sidebar-card">
                    <h3>Quick Links</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><a href="dashboard.php">Dashboard</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="events.php">Events</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="my-checkins.php">My Check-ins</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="profile.php">Profile</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="account-settings.php">Settings</a></li>
                        <?php if (Auth::hasRole(['admin'])): ?>
                        <li style="margin-bottom: 0.5rem;"><a href="admin/users.php">User Management</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="../admin/events.php">Event Management</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="sidebar-card">
                    <h3>System Information</h3>
                    <ul style="list-style: none; padding: 0; font-size: 0.9rem;">
                        <li style="margin-bottom: 0.25rem;"><strong>Version:</strong> 3.0</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Last Update:</strong> August 2025</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Uptime:</strong> 99.9%</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Users:</strong> <?php 
                            $stmt = $db->prepare("SELECT COUNT(*) FROM Users WHERE is_active = 1");
                            $stmt->execute();
                            echo $stmt->fetchColumn(); 
                        ?></li>
                        <li style="margin-bottom: 0.25rem;"><strong>Events:</strong> <?php 
                            $stmt = $db->prepare("SELECT COUNT(*) FROM Events");
                            $stmt->execute();
                            echo $stmt->fetchColumn(); 
                        ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- End main-content -->
    
    <script>
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('span:last-child');
            
            if (answer.classList.contains('show')) {
                answer.classList.remove('show');
                icon.textContent = '+';
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer.show').forEach(el => {
                    el.classList.remove('show');
                    el.previousElementSibling.querySelector('span:last-child').textContent = '+';
                });
                
                answer.classList.add('show');
                icon.textContent = '−';
            }
        }
        
        function searchHelp() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const sections = document.querySelectorAll('.help-section');
            
            if (!query) {
                sections.forEach(section => section.style.display = 'block');
                return;
            }
            
            sections.forEach(section => {
                const text = section.textContent.toLowerCase();
                if (text.includes(query)) {
                    section.style.display = 'block';
                    // Highlight search terms (simplified)
                } else {
                    section.style.display = 'none';
                }
            });
        }
        
        // Contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // In a real implementation, this would submit to a support system
            alert('Thank you for your message! We\'ll get back to you within 24 hours.');
            this.reset();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            } else if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            } else if (e.altKey && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'events.php';
            } else if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'profile.php';
            }
        });
        
        // Smooth scrolling for anchor links
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
