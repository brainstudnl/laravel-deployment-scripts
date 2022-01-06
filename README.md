## Introduction

When deploying new code you'll sometimes need to execute one time scripts, commands or queries to activate new
functionalities. With a growing application, it is easy to lose sight of which things should be executed for a given
deployment. Laravel already has migrations which run after executing the artisan migrate command, but these migrations
are aimed at database operations only.

The Laravel one time deployment scripts package aims to solve this problem by offering a migration-like way of
registering scripts, commands and queries that should be executed. By simply registering the deployment scripts execute
command, the package will check whether there are any scripts that should be executed. Once a script has been executed
it will be registered in the database so that it won't run again.

## Example

The code snippet below is an example of how the deployment script is configured. A regular or an anonymous class is
created which extends the DeploymentScript base class. Then it registers the up and down methods to contain the
deployment tasks that need to be executed, and finally within those methods the desired tasks are executed.

```php
return new class extends DeploymentScript {
	public function up()
	{
		$this->command('mycommand:trigger');

		$this->query('UPDATE `table` SET `foo` = 1 WHERE `baz` IS NULL');
	}
	
	public function down()
	{
		$this->closure(function () {
			(new CustomActionClass())->execute();
		});
	}
} 
```

## Installation

You can install the package via composer:

```bash
composer require brainstud/laravel-deployment-scripts
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="deployment-scripts-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="deployment-scripts-config"
```

This is the content of the published config file:

```php
return [
    'table_name' => 'deployment_scripts_log',
];
```

## Usage

### Available commands

| Command | Description |
| --- | --- |
| deployment-script:make {name} | Create a new deployment script class |
| deployment-script:execute | Execute the deployment scripts |
| deployment-script:rollback | Roll back the deployment scripts by one iteration |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
