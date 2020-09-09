# Laravel Datatables Easy
If you're finding an easy way to uso Datatables, specially with server-side processing, DatatablesEasy is the answer.  
It will handle all front-end and back-end routines, automatically.

## Installation

**Step 1** - Install the package:
~~~
composer require gianclaudiooliveira/datatableseasy
~~~
**P.S.:** If you're under Laravel 5.3 or older, you need to register the Service Provider in the **config/app.php**:
~~~
'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        ...
        DatatablesEasy\DatatablesEasyServiceProvider::class,
        ...
]
~~~

**Step 2** - Publish package assets:
~~~
php artisan vendor:publish
~~~

**Step 3** - Include CSS and JS files in your HTML template:
~~~
    	<link rel="stylesheet" href="{{ URL::asset('vendor/datatableseasy/datatablesEasy/datatablesEasy.css') }}">
~~~
~~~
    	<script src="{{ URL::asset('vendor/datatableseasy/datatablesEasy/datatablesEasy.js') }}"></script>
~~~
**P.S.:** You should have the Datatables plugin already installed and working.

<big><big>**And NOW, you`re ready!**</big></big>
<br/>
<br/>
## Implementation
<br/>
<br/>

**Exemple 1:** You have a table People and model Person:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		columns: [
			{name: "firstname"},
			{name: "lastname"},
			{name: "birthdate", "className": "text-center"},
			{name: "address"}
		]

	});

</script>
~~~

<br/>
<br/>

**Exemple 2:** You have the same table People and model Person, but you want to include some foreign fields:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		columns: [
			{name: "firstname"},
			{name: "lastname"},
			{name: "birthdate", "className": "text-center"},
			{name: "genders.title", "className": "text-center"},
			{name: "address"},
			{name: "states.name"},
			{name: "cities.name"}
		]

	});

</script>
~~~
**P.S.:**: Use syntax "<table_name>.<field_name>". It's **mandatory** having foreign key relatioship between these tables.

<br/>
<br/>

**Exemple 3:** Now, you want to filter only married people:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		fixedFilters: [
			"married": true,
		],
		columns: [
			{name: "firstname"},
			{name: "lastname"},
			{name: "birthdate", "className": "text-center"},
			{name: "genders.title", "className": "text-center"},
			{name: "address"},
			{name: "states.name"},
			{name: "cities.name"}
		]

	});

</script>
~~~
## -- DOCUMENTATION UNDER CONSTRUCTION --
