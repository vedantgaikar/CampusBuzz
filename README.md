# CampusBuzz - Campus Event Management System

CampusBuzz is a comprehensive web application for managing campus events, allowing users to create, discover, and register for events happening around their campus.

## Features

- User registration and authentication
- Event creation and management
- Event discovery and search
- Event registration for users
- Responsive design for all devices

## Technologies Used

- HTML5
- CSS3
- PHP
- MySQL
- XAMPP (Apache, MySQL, PHP)

## Installation

1. **Install XAMPP**

   Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

2. **Clone the Repository**

   Clone this repository to your XAMPP htdocs folder:

   ```
   git clone https://github.com/yourusername/campusbuzz.git C:/xampp/htdocs/campusbuzz
   ```

   Or download and extract the ZIP file to `C:/xampp/htdocs/campusbuzz`

3. **Start XAMPP Services**

   Start the Apache and MySQL services using the XAMPP Control Panel.

4. **Create the Database**

   - Open your web browser and navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   - Create a new database named `campusbuzz`
   - Import the `database.sql` file from the project folder to set up the database structure

5. **Configure Database Connection**

   Edit the `includes/config.php` file and update the database connection details if needed:

   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'campusbuzz');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Update if you've set a password for your MySQL
   ```

6. **Access the Application**

   Open your web browser and navigate to [http://localhost/campusbuzz](http://localhost/campusbuzz)

## Default Admin Account

- Email: admin@campusbuzz.com
- Password: admin123

## Directory Structure

```
campusbuzz/
├── assets/
│   └── images/
├── css/
│   └── styles.css
├── includes/
│   ├── config.php
│   ├── db_connect.php
│   ├── header.php
│   └── footer.php
├── pages/
│   ├── login.php
│   ├── signup.php
│   ├── events.php
│   └── logout.php
├── index.php
├── database.sql
└── README.md
```

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

Your Name

## Acknowledgements

- Font Awesome for icons
- Placeholder.com for placeholder images
