-- Create main database
CREATE DATABASE IF NOT EXISTS laravel;

-- Create testing database
CREATE DATABASE IF NOT EXISTS laravel_testing;

-- Grant permissions to laravel user for both databases
GRANT ALL PRIVILEGES ON laravel.* TO 'laravel'@'%' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON laravel_testing.* TO 'laravel'@'%' IDENTIFIED BY 'secret';

-- Grant permissions to root user for both databases (for testing)
GRANT ALL PRIVILEGES ON laravel.* TO 'root'@'%' IDENTIFIED BY 'root';
GRANT ALL PRIVILEGES ON laravel_testing.* TO 'root'@'%' IDENTIFIED BY 'root';

FLUSH PRIVILEGES;