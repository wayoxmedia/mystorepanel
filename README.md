# My Store Panel
A restful API for a store admin to manage store products, orders, and customers.

## PreRequisites

* git
* ssh
* docker

## Installation

You must have Docker installed and running properly.

### Folder Structure

You should end up with a folder 📁 structure like this:

    YourDevFolder
    |- template1
    |- ...
    |- template7
    |- web-templates
    |- orchestration
    |- mystorepanel     <- This repo

### Getting Started

Go to YourDevFolder and clone this repo using git

```sh
git clone git@github.com:wayoxmedia/mystorepanel.git
```
cd into your app

```sh
cd mystorepanel
```

get a copy of the actual .env file form admins or create your own .env file and edit some values.
```sh
cp .env.example .env
```

run docker build

```sh
docker compose --env-file .env build
```

This may take some minutes if this is your first install, images are been downloaded.

Now, bring up the environment.

```sh
docker-compose up -d
```

Check the containers are properly running

```sh
docker ps
```

### Composer, .env and artisan

Now that you have successfully built the containers, you need to ssh into your container and run composer install and some other commands.

```sh
cd html
docker exec -it mystorepanel bash
composer install
```
After composer install, you need to create a .env file (Stay inside the container).

```sh
cp .env.example .env
```

Make sure you define the testing environment in your .env file.

```sh
cp .env.testing.sample .env.testing
nano .env.testing
```
And set the APP_ENV variable to testing.
```text
APP_ENV=testing
```

run artisan key:generate and jwt:secret

```sh
php artisan key:generate
php artisan jwt:secret
```

now, update the .env file with your DB credentials.

```sh
nano .env
```
Locate and edit the DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD variables.

```dotenv
DB_CONNECTION=REQUEST_TO_ADMINS
DB_HOST=REQUEST_TO_ADMINS
DB_PORT=REQUEST_TO_ADMINS
DB_DATABASE=REQUEST_TO_ADMINS
DB_USERNAME=REQUEST_TO_ADMINS
DB_PASSWORD=REQUEST_TO_ADMINS
```

While there, you can also check if exists or edit the JWT_TTL variable.

```dotenv
JWT_TTL=ASK_ADMINS
```
Make sure you have a recent version of the .env file, as some variables may change over time. Contact the admins if you need to update your .env file.

Using local (End to End) encryption is recommended, make sure you use the following variables in your .env file:

```dotenv
SERVICE_TOKEN=replace-with-a-long-random-string
```
To do so, run the following command to generate a random string:

```sh
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Copy this string and paste it in the SERVICE_TOKEN variable in your .env file AND also in the .env file of the web-templates folder. Both .env files must have the same SERVICE_TOKEN value.


### Updating your hosts file
MacOS & Linux
In your terminal, run
```sh
sudo nano /etc/hosts
```
PC
```
Open [SystemRoot]\system32\drivers\etc\hosts and edit the file with your text editor with admin privileges.
```
Add the following lines at the end of this hosts file
```
127.0.0.1     mystorepanel.test
```
MacOS & Linux: 'Ctrl+O' then 'y' to save and 'Ctrl+X' to quit nano.
PC: Save and quit your editor.

After these steps, you may need to flush your dns.

Finally, navigate with your browser to the app home page.

[http://mystorepanel.test](http://mystorepanel.test)

You should see the welcome page, check it is properly working.


### Recommendations
#### Linting your code
```sh
docker exec -it mystorepanel bash
```
Use any of these commands to lint your code.
```sh
sh phpcs.sh s app
sh phpcs.sh s tests/Feature
sh phpcs.sh s tests/Unit
```
#### Running your tests
```sh
docker exec -it mystorepanel bash
```
Use any of these commands to run your tests.
```sh
sh tests.sh tests/Unit
sh tests.sh tests/Feature
sh tests.sh tests --slack
sh tests.sh tests --slack-coverage
sh tests.sh tests --coverage
sh tests.sh tests --isolation
sh tests.sh tests --filter upd --debug
sh tests.sh tests --ci # in dev/testing, do not use
```

### That's it! Welcome to your restful API Environment.

### Recommendations

* Use Visual Studio Code with the Remote - Containers extension to open your project in a container.
* Use the Docker extension to manage your containers, images, volumes, networks and containers.


### Dont forget
* Run these commands after pulling new code, changing your .env file or when something is not working as expected.
```sh
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
Happy coding!

