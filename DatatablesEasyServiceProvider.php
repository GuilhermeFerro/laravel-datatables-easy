<?php

namespace DatatablesEasy;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class DatatablesEasyServiceProvider extends ServiceProvider
{

	protected $defer = false;

	public function boot() {

		if (!$this->app->routesAreCached()) {
			require __DIR__."/routes.php";
			//$this->loadRoutesFrom(__DIR__.'/routes.php');
		}

		//$this->loadViewsFrom(__DIR__."/view", "docbuilder");
		$this->publishes([
			__DIR__.'/config/datatableseasy.php' => config_path('datatableseasy.php'),
			__DIR__.'/public' => public_path('vendor/datatableseasy'),
			//__DIR__.'/migration' => database_path('migrations'),
		]);

		Blade::directive("DatatablesEasyCSS", function(){
			return "<link href='/vendor/datatableseasy/datatablesEasy/datatablesEasy.css' rel='stylesheet' type='text/css'/>";
		});

		Blade::directive("DatatablesEasyJS", function(){
			return 	"<script src='/vendor/datatableseasy/datatablesEasy/datatablesEasy.js'></script>".
					"<script src='/vendor/datatableseasy/datatablesEasy/datatables_ptbr.js'></script>";
		});

		// migrations, se existir
		//$this->loadMigrationsFrom(__DIR__.'/migration');
	}

	public function register() {

		// para dar uma resposta ao "\App::make('alias1')" no meio da aplicação
		/*
		$this->app->bind(["alias1", "alias2",...], function($app){
			return new MinhaClasse();
		});
		$this->app->singleton(["alias1", "alias2",...], function($app){
			return new MinhaClasse();
		});
		*/
	}

	/*
	public function provides() {
		return ["alias1", "alias2"];
	}
	*/
}

