## My Store Admin
A restful API for a store admin to manage products, orders, and customers.

### PreRequisites

* git
* ssh
* docker

### Installation

You must have Docker installed and running properly.

#### Folder Structure

You should have already a folder structure like this:

    YourDevFolder

    |- EgleesGourmet

    |- orchestration

    |- myStoreAdmin

Go to YourDevFolder and clone this repo using git

`git clone git@github.com:wayoxmedia/myStoreAdmin.git`

cd into your app

`cd myStoreAdmin`

run docker build

`docker compose build`

This may take some minutes if this is your first install, images are been downloaded.

Now, bring up the environment.

`docker-compose up -d`

Check the containers are properly running

`docker ps`

#### Composer, .env and artisan

Now that you have successfully built the containers, you need to run composer install.

```sh
docker exec -it deepdevs bash
composer install
```
After composer install, you need to create a .env file (Stay inside the container).

```sh
cp .env.example .env
```

run artisan key:generate

```sh
php artisan key:generate
```

now, update the .env file with your DB credentials.

```sh
nano .env
```
Locate and edit the DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD variables.

```text
DB_CONNECTION=REQUEST_TO_ADMINS
DB_HOST=REQUEST_TO_ADMINS
DB_PORT=REQUEST_TO_ADMINS
DB_DATABASE=REQUEST_TO_ADMINS
DB_USERNAME=REQUEST_TO_ADMINS
DB_PASSWORD=REQUEST_TO_ADMINS
```

#### Updating your hosts file
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
127.0.0.1     deepdevs.test
```
MacOS & Linux: 'Ctrl+O' then 'y' to save and 'Ctrl+X' to quit nano.
PC: Save and quit your editor.

After these steps, you may need to flush your dns.

Finally, navigate with your browser to the app home page.

`http://deepdevs.test`

You should see the welcome page, check it is properly working.


### Recommendations
#### Linting your code
```sh
docker exec -it deepdevs bash
sh phpcs.sh s app
sh phpcs.sh s tests
```
#### Running your tests
```sh
docker exec -it deepdevs bash
sh tests.sh tests/Unit
sh tests.sh tests/Feature
```

### That's it! Welcome to your restful API Environment.

Happy coding!

