# System Includes

This directory contains shared include files and common components that provide consistent functionality across the RFID Check-in System.

## Components

### Navigation System
- **`navigation.php`** - Primary navigation component with role-based menu generation
- Dynamic menu rendering based on user permissions
- Responsive navigation with mobile optimization
- Active page detection and highlighting
- User authentication status integration

### Theme & UI Components
- **`theme_script.php`** - Theme management and UI enhancement scripts
- Dark/light theme switching functionality
- User preference persistence
- Dynamic CSS variable management
- Accessibility enhancement features

## Navigation Architecture

### Role-Based Navigation
The navigation system dynamically generates menu items based on:
- **User Authentication Status** - Guest vs. authenticated user menus
- **User Role Permissions** - Admin, manager, and user-specific menu items
- **Feature Access Control** - Module-specific access validation
- **Context Awareness** - Current page and workflow context

### Navigation Structure
```php
// Navigation hierarchy example
Dashboard → [All authenticated users]
├── My Check-ins → [All users]
├── Events → [All users]
├── Profile → [All users]
└── Account Settings → [All users]

Administration → [Admin/Manager only]
├── Users → [Admin only]
├── Events Management → [Admin/Manager]
├── Reports → [Admin/Manager]
├── Groups → [Admin/Manager]
└── System Settings → [Admin only]
```

### Responsive Design
- **Mobile-First Approach** - Optimized for mobile devices
- **Progressive Enhancement** - Enhanced features for larger screens
- **Touch-Friendly Interface** - Appropriate touch targets and interactions
- **Accessibility Compliance** - Keyboard navigation and screen reader support

## Theme Management System

### Theme Engine Features
- **Multiple Theme Support** - Light, dark, and custom themes
- **User Preference Storage** - Individual user theme preferences
- **System Default Management** - Organization-wide theme defaults
- **Real-Time Switching** - Instant theme changes without page reload

### CSS Variable System
```css
:root {
  --primary-color: #2563eb;
  --secondary-color: #64748b;
  --background-color: #ffffff;
  --text-color: #1e293b;
}

[data-theme="dark"] {
  --background-color: #0f172a;
  --text-color: #f1f5f9;
}
```

### Theme Customization
- **Brand Color Management** - Organization-specific color schemes
- **Typography Control** - Font family and sizing options
- **Layout Customization** - Spacing and component sizing
- **Animation Preferences** - Motion reduction for accessibility

## Integration Features

### Security Integration
- **CSRF Token Management** - Automatic token inclusion in navigation forms
- **Session Validation** - Real-time session status checking
- **Access Control Validation** - Menu item access verification
- **Security Headers** - Proper security header implementation

### Performance Optimization
- **Caching Strategy** - Navigation menu caching for improved performance
- **Lazy Loading** - Deferred loading of non-critical navigation elements
- **Resource Optimization** - Minimized CSS and JavaScript delivery
- **CDN Integration** - Content delivery network support

## Accessibility Features

### WCAG 2.1 Compliance
- **Keyboard Navigation** - Full keyboard accessibility
- **Screen Reader Support** - Proper ARIA labels and descriptions
- **High Contrast Mode** - Enhanced visibility options
- **Focus Management** - Clear focus indicators and logical tab order

### User Experience Enhancement
- **Skip Links** - Content skip navigation for screen readers
- **Landmark Roles** - Proper HTML landmark identification
- **Alternative Text** - Comprehensive alt text for images and icons
- **Language Support** - Multi-language navigation support

## Maintenance & Updates

### Code Standards
- **Consistent Coding Style** - PSR-12 compliance for PHP code
- **Documentation Standards** - Comprehensive inline documentation
- **Version Control** - Proper git workflow and change tracking
- **Testing Procedures** - Automated testing for navigation functionality

### Update Procedures
- **Feature Addition** - Process for adding new navigation items
- **Permission Updates** - Role-based access control modifications
- **Theme Updates** - Theme system enhancement and customization
- **Security Updates** - Security patch application and validation

## Cross-Platform Compatibility

### Browser Support
- **Modern Browsers** - Chrome, Firefox, Safari, Edge (latest versions)
- **Progressive Enhancement** - Graceful degradation for older browsers
- **Mobile Browsers** - Optimized mobile browser experience
- **Accessibility Tools** - Screen reader and assistive technology compatibility

### Device Compatibility
- **Desktop Computers** - Full-featured desktop experience
- **Tablets** - Touch-optimized tablet interface
- **Mobile Phones** - Mobile-first responsive design
- **Assistive Devices** - Compatibility with accessibility hardware
