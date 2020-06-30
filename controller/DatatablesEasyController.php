<?php

namespace DatatablesEasy\controller;

use DatatablesEasy\Helpers\DatatablesEasy;
use App\Http\Controllers\Controller;

class DatatablesEasyController extends Controller
{
	public function page ()
	{
		return DatatablesEasy::getResult();
	}
}
