# EVSU Voice - Campus Feedback Platform

A comprehensive web-based platform that empowers the EVSU community to share insights, ideas, and feedback to shape a better campus together.

## Features

### For Students

- **Browse Suggestions**: View all community suggestions with voting capability
- **Submit Suggestions**: Share ideas anonymously or with attribution
- **Vote on Suggestions**: Support suggestions you believe in (one vote per suggestion)
- **My Suggestions**: Track your submitted suggestions and their progress
- **User Authentication**: Secure login with EVSU email addresses (@evsu.edu.ph)

### For Administrators

- **Dashboard**: Overview of platform statistics and recent activity
- **Manage Suggestions**: Review, respond to, and update suggestion status
- **Export Data**: Generate CSV reports for analysis and reporting
- **Status Management**: Update suggestions through various stages (Pending → New → Under Review → In Progress → Implemented)

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Responsive Design
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Icons**: RemixIcon
- **Styling**: Custom CSS with CSS Variables for theming

## Installation

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Setup Instructions

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd evsu-voice
   ```

2. **Database Setup**

   - Create a MySQL database named `evsu_voice`
   - Import the database schema:

   ```bash
   mysql -u username -p evsu_voice < database/schema.sql
   ```

3. **Configure Database Connection**

   - Edit `config/database.php`
   - Update database credentials:

   ```php
   private $host = 'localhost';
   private $db_name = 'evsu_voice';
   private $username = 'your_username';
   private $password = 'your_password';
   ```

4. **Set File Permissions**

   ```bash
   chmod 755 assets/
   chmod 644 *.php
   ```

5. **Access the Application**
   - Open your web browser
   - Navigate to your web server URL
   - Default admin login: `admin@evsu.edu.ph` / `password`

## File Structure

```
evsu-voice/
├── admin/
│   ├── dashboard.php          # Admin dashboard
│   ├── manage-suggestions.php # Suggestion management
│   └── export-data.php        # Data export functionality
├── assets/
│   ├── css/
│   │   └── styles.css         # Main stylesheet
│   ├── js/
│   │   └── main.js           # JavaScript functionality
│   └── img/                  # Images and assets
├── config/
│   └── database.php          # Database configuration
├── database/
│   └── schema.sql            # Database schema
├── includes/
│   ├── auth.php              # Authentication system
│   ├── header.php            # Common header
│   └── footer.php            # Common footer
├── index.php                 # Homepage
├── login.php                 # Login/Registration page
├── logout.php                # Logout functionality
├── browse-suggestions.php    # Browse suggestions page
├── submit-suggestion.php     # Submit suggestion form
├── my-suggestions.php        # User's suggestions page
└── README.md                 # This file
```

## User Roles

### Student Users

- Must register with valid @evsu.edu.ph email
- Can submit suggestions (anonymous or attributed)
- Can vote on suggestions (once per suggestion)
- Can view their submission history and status

### Admin Users

- Access to admin dashboard
- Can review and respond to suggestions
- Can change suggestion status
- Can export data for reporting
- Cannot submit suggestions (admin-only role)

## Suggestion Workflow

1. **Pending**: Newly submitted, awaiting admin review
2. **New**: Approved by admin, visible to community
3. **Under Review**: Being actively considered by administration
4. **In Progress**: Accepted for implementation
5. **Implemented**: Successfully put into action
6. **Rejected**: Not approved (with admin explanation)

## Security Features

- Password hashing using PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with `htmlspecialchars()`
- Session-based authentication
- Email validation for EVSU domain
- CSRF protection considerations

## Customization

### Themes

The platform supports light and dark themes. Theme preference is stored in localStorage.

### Categories

Default categories are provided but can be modified in the database:

- Academic Affairs
- Student Services
- Campus Facilities
- Technology
- Student Life
- Administration
- Library Services
- Health and Safety
- Transportation
- Food Services
- Other

### Styling

CSS variables are used for easy customization:

```css
:root {
  --first-color: hsl(43, 100%, 52%);
  --title-color: hsl(230, 70%, 16%);
  --text-color: hsl(230, 16%, 45%);
  /* ... more variables */
}
```

## API Endpoints

The platform uses form-based submissions. Key endpoints:

- `POST /login.php` - User authentication
- `POST /submit-suggestion.php` - Submit new suggestion
- `POST /browse-suggestions.php` - Vote on suggestions
- `POST /admin/manage-suggestions.php` - Update suggestion status
- `GET /admin/export-data.php?export=csv` - Export data

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## Support

For technical support or questions:

- Email: jetvenson.romero@evsu.edu.ph
- Create an issue in the repository

## License

This project is developed for Eastern Visayas State University (EVSU) and is intended for educational and institutional use.

## Changelog

### Version 1.0.0

- Initial release
- User authentication system
- Suggestion submission and voting
- Admin dashboard and management
- Data export functionality
- Responsive design
- Dark/light theme support
