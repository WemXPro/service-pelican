# Pelican Integration for WemX

After you have installed the integration, please follow the steps below.

1. Make sure you have enabled the service in Admin Area -> Services -> click "Enable" for Pelican
2. run the following command in /var/www/wemx
```
php artisan vendor:publish --tag=pelican-config
```

3. This command will create a new config file in /var/www/wemx/config called pelican.php, Edit this file
4. In this file you can create multiple locations and add nodes that below to those locations
