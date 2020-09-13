# Laravel Datatables Easy
If you're finding an easy way to uso Datatables, specially with server-side processing, DatatablesEasy is the answer.  
It will handle all front-end and back-end routines, automatically.

## Installation
<br/>

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
Or, being more specific:
~~~
php artisan vendor:publish --provider="DatatablesEasy\DatatablesEasyServiceProvider"
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

### Basic start

You have a table People and model Person:
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

### Columns with foreign fields

You have the same table People and model Person, but you want to include some foreign fields:
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

### You got some fixed filters (filters always applied)

**A.** Now, you want to see people who has no brothers:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		fixedFilters: [
			{"key": "brothers", "item": "0"}
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
<br/>
<br/>

**B.** Maybe, people with no brothers and children:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		fixedFilters: [
			{"key": "brothers", "item": "0"},
			{"key": "children", "item": "0"}
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

<br/>
<br/>

**C.** If you need to see only people who was born after 01/15/2000:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		fixedFilters: [
			{"key": "_operator", "item": ["birthdate", ">", "2000-01-15"]}
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

<br/>
<br/>

**D.** Now, people who was born after 01/15/2000, has no child and address is NOT NULL:
~~~
<script type="text/javascript">

	$("#tblMain").datatablesEasy({

		modelname: "Person",
		fixedFilters: [
			{"key": "_operator", "item": ["birthdate", ">", "2000-01-15"]},
			{"key": "children", "item": "0"},
			{"key": "_notnull", "item": "address"}
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

<br/>
<br/>

### Advanced fixed filters

**"_group"** -> "AND" group, with 1 or more AND-separated objects:
	
	Syntax:
	{"key": "_group", "item": [
		{"key": "children", "item": "0"},
		{"key": "_notnull", "item": "address"}
	]}

	SQL-like:
	SELECT .... FROM .... WHERE .... AND (children = "0" AND address IS NOT NULL)

	Eloquent-like:
	Person::where(function($query){
		$query->where("children", 0)
		      ->whereNotNull("address");
	});


**"_orgroup"** -> "OR" group, but still AND-separated:
	
	Syntax:
	{"key": "_orgroup", "item": [
		{"key": "children", "item": "0"},
		{"key": "brothers", "item": "1"}
	]}

	SQL-like:
	SELECT .... FROM .... WHERE .... OR (children = "0" AND brothers = 1)

	Eloquent-like:
	Person::orWhere(function($query){
		$query->where("children", 0)
		      ->where("brothers", 1);
	});


	Obs.: To do a simple OR (with no group), you should use this group, but with a single member.


**"_operator"**	-> Like shown in a exemple above, a way to specify an operator:
	
	Syntax:
	{"key": "_operator", "item": ["birthdate", ">", "2000-01-15"]}

	SQL-like:
	SELECT .... FROM .... WHERE birthdate > "2000-01-15"

	Eloquent-like:
	Person::where("birthdate", ">", "2000-01-15");
	

**"_null", "_notnull"**	-> NULL / NOT NULL clause:
	
	Syntax:
	{"key": "_notnull", "item": "address"}

	SQL-like:
	SELECT .... FROM .... WHERE address is NOT NULL

	Eloquent-like:
	Person::whereNotNull("address");


**"_diff"** -> To simplify "<>" operator:
	
	Syntax:
	{"key": "_diff", "item": ["brothers", "0"]}

	SQL-like:
	SELECT .... FROM .... WHERE brothers <> "0"

	Eloquent-like:
	Person::where("brothers", "<>", 0);
	

**"_between", "_notbetween"** -> value/item is a 3-item array. First, the target field. 2nd and 3rd, the values to be applied to BETWEEN:
	
	Syntax:
	{"key": "_between", "item": ["birthdate", "1980-01-01", "1999-12-31"]}

	SQL-like:
	SELECT .... FROM .... WHERE birthdate BETWEEN "1980-01-01" AND "1999-12-31"

	Eloquent-like:
	Person::whereBetween("birthdate", ["1980-01-01", "1999-12-31"]);
	

**"_in", "_notin"** -> value/item is an array with at least 1 item. First, the target field. 2nd, 3rd, 4th.... the values to be applied to IN:
	
	Syntax:
	{"key": "_in", "item": ["code", "A01", "B00", "C99"...]}

	SQL-like:
	SELECT .... FROM .... WHERE code IN ("A01", "B00", "C99"...)

	Eloquent-like:
	Person::whereIn("code", ["A01", "B00", "C99"...]);
	

## -- DOCUMENTATION UNDER CONSTRUCTION --
