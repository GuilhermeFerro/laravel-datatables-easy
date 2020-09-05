<?php

namespace DatatablesEasy\Helpers;

use Illuminate\Support\Facades\DB;

class DatatablesEasy
{
	private $allJoins = [];
	private $selectedTemplateFields = [];
	private $params;
	private $dbDefinitions;
	private	$modelClass;
	private	$modelTableName;
	private $modelTablePk;

    private $registros_count;
    private $registros_filtered_count;


	/**
	 * Processa e aplica em uma $query filtros padrão array. Futuramente, criar um pacote Laravel para este método.
	 * @param type $query
	 * @param type $filter
	 * @return void
	 */
    private function processFilterArray($query, $filter)
    {
        if (!is_array($filter)) return;

        $isIndexed = (array_values($filter) === $filter);

        foreach ($filter as $key => $item)
        {
			if ($isIndexed){
				$_key = $item["key"];
				$_item = $item["item"];
			}
			else {
				$_key = $key;
				$_item = $item;
			}

			if ($_key == "_group")
            {
                $query->where(function ($query) use ($_item) {
                    $this->processFilterArray($query, $_item);
                });
                continue;
            }

            if ($_key == "_orgroup")
            {
                $query->orWhere(function ($query) use ($_item) {
                    $this->processFilterArray($query, $_item);
                });
                continue;
            }

            if ($_key == "_null")
            {
				$fieldNameFull = $this->extractFieldName($_item);
				$this->applyJoins($query, $_item);

                $query->whereNull($fieldNameFull);
                continue;
            }

            if ($_key == "_notnull")
            {
				$fieldNameFull = $this->extractFieldName($_item);
				$this->applyJoins($query, $_item);

                $query->whereNotNull($fieldNameFull);
                continue;
            }

            if ($_key == "_diff" && is_array($_item) && count($_item) == 2)
            {
				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

                $query->where($fieldNameFull, "!=", $_item[1]);
                continue;
            }

            if ($_key == "_between" && is_array($_item) && count($_item) == 3)
            { // between explícito

				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

                $query->whereBetween($fieldNameFull, [$_item[1], $_item[2]]);
                continue;
            }

            if ($_key == "_notbetween" && is_array($_item) && count($_item) == 3)
            { // between explícito

				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

                $query->whereNotBetween($fieldNameFull, [$_item[1], $_item[2]]);
                continue;
            }

            if ($_key == "_in" && is_array($_item) && count($_item) >= 1)
            { // in explícito

				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

				array_shift($_item);

                $query->whereIn($fieldNameFull, $_item);
                continue;
            }

            if ($_key == "_notin" && is_array($_item) && count($_item) >= 1)
            { // in explícito

				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

				array_shift($_item);

                $query->whereNotIn($fieldNameFull, $_item);
                continue;
            }

            /*
            if ($_key == "_or")
            {
				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

                if (count($_item) == 2)
                    $query->orWhere($fieldNameFull, $_item[1]);
                if (count($_item) == 3)
                    $query->orWhere($fieldNameFull, $_item[1], $_item[2]);
                continue;
            }
            */

            if ($_key == "_operator" && is_array($_item) && count($_item) == 3)
            { // operador especificado

				$fieldNameFull = $this->extractFieldName($_item[0]);
				$this->applyJoins($query, $_item[0]);

                $query->where($fieldNameFull, $_item[1], $_item[2]);
                continue;
            }

			// Obs.: a partir desse ponto, $_key com certeza é o fieldname (com ou sem os joins)

			$fieldNameFull = $this->extractFieldName($_key);
			$this->applyJoins($query, $_key);

            if (is_array($_item) && count($_item) == 2)
            { // between
                $query->whereBetween($fieldNameFull, $_item);
                continue;
            }

            if (is_array($_item) && count($_item) > 2)
            { // in
                $query->whereIn($fieldNameFull, $_item);
                continue;
            }

            // where comum
            $query->where($fieldNameFull, $_item);
        }
    }

	/**
	 * Retorna o filtro geral enviado no request
	 * @return string
	 */
	private function getRequestedGeneralFilter()
	{
		$request = request();
		return $request->input('search')['value'];
	}

	/**
	 * Retorna os filters de colunas enviados no request
	 * @return array
	 */
	private function getRequestedColumns()
	{
		$request = request();
		return $request->input('columns');
	}

	/**
	 * Retorna os extra filters (filtros fora da tabela / que não são de colunas) enviados no request
	 * @return array
	 */
	private function getRequestedExtraFilters()
	{
		$request = request();
		return $request->input('extradata');
	}

	/**
	 * Retorna os parâmetros e options enviados no request
	 * @return array
	 */
	private function getRequestedParams()
	{
		$request = request();
		return $request->input('extraparams');
	}

	/**
	 * Retorna o parâmetro DRAW enviado no request
	 * @return int
	 */
	private function getRequestedDraw()
	{
		$request = request();
		return $request->input('draw');
	}

	/**
	 * Retorna o parâmetro START enviado no request
	 * @return int
	 */
	private function getRequestedStart()
	{
		$request = request();
		return $request->input('start');
	}

	/**
	 * Retorna o parâmetro LENGTH enviado no request
	 * @return int
	 */
	private function getRequestedLength()
	{
		$request = request();
		return $request->input('length');
	}

	/**
	 * Retorna o parâmetro ORDER enviado no request
	 * @return array
	 */
	private function getRequestedOrder()
	{
		$request = request();
		return $request->input('order');
	}

	private function invertMatches($mat)
	{
		$resmat = [];
		foreach ($mat as $idx => $item)
			foreach ($item as $subidx => $subitem)
				$resmat[$subidx][$idx] = $subitem;
		return $resmat;
	}

	/**
	 * Desmembra nomes das tabelas dos nomes dos campos como vêem da definição
	 * @param type $fieldname
	 * @return array -> [["table" => "", "join" => ""]...]
	 */
	private function extractTablesNames($fieldname)
	{
		$matches = [];
		$result = [];
		preg_match_all ("/([a-z0-1_-]+)([\.\,\;]{1})/mi", $fieldname, $matches);
		$matches = $this->invertMatches($matches);
		foreach ($matches as $item) {
			$join = "";
			switch ($item[2]){
				case ".": $join = "leftJoin"; break;
				case ",": $join = "rightJoin"; break;
				case ";": $join = "join"; break; // inner
			}
			$result[] = ["table" => $item[1], "join" => $join];
		}

		return $result;
	}

	/**
	 * Retira oa nomes das tabelas e retorna somente o nome do campo
	 * @param type $fieldname
	 * @return string
	 */
	private function extractFieldName($fieldname)
	{
		$joins = $this->extractTablesNames($fieldname);
		$matches = [];
		preg_match_all ("/([a-z0-1_-]+)$/i", $fieldname, $matches);
		$matches = $this->invertMatches($matches);
		$purefieldname = $matches[0][1] ?? "";
		$directtable = count($joins) > 0 ? end($joins)["table"]."." : $this->modelTableName.".";
		return $directtable.$purefieldname;
	}

	/**
	 * Aplica joins na $query, apenas para as tabelas vindas em $lineJoin que ainda não foram juntadas.
	 * @param type $query
	 * @param type $lineJoin
	 */
	private function applyJoins($query, $lineJoin)
	{
		$joins = $this->extractTablesNames($lineJoin);
		$lastTable = $this->modelTableName;
		foreach ($joins as $item){
			$isInList = (array_search($item["table"], $this->allJoins) === false) ? false : true;

			if ($isInList) {
				$lastTable = $item["table"];
				continue;
			}

			$joinMethod = $item["join"];
			$rt = $item["table"];
			$lt = $lastTable;
			if (isset($this->tableDefinitions($lt)["relations"][$rt])){
				$rk = $this->tableDefinitions($rt)["pk"];
				$lk = $this->tableDefinitions($lt)["relations"][$rt]["fk"];
			}
			else {
				$rk = $this->tableDefinitions($rt)["relations"][$lt]["fk"];
				$lk = $this->tableDefinitions($lt)["pk"];
			}
			$this->allJoins[] = $rt;
			$query->$joinMethod($rt, $lt.".".$lk, "=", $rt.".".$rk);
			$lastTable = $rt;
		}
	}

	/**
	 * Gera a Session Key para troca de dados com a session.
	 * @return string
	 */
	private function genSessionKey()
	{
		return $this->modelClass."dteDBDef";
	}

	/**
	 * Salva definições do BD na session.
	 * @param array $definitions
	 */
	private function saveDatabaseDefinitions()
	{
		session([$this->genSessionKey() => $this->dbDefinitions]);
	}

	/**
	 * Recupera definições do BD na session.
	 * @return array
	 */
	private function getDatabaseDefinitions()
	{
		return session($this->genSessionKey()) ?? [];
	}

	/**
	 * Vai ao banco pegar os metadados da tabela e relacionamentos.
	 * @param string $tableName
	 * @return array
	 */
	private function fetchTableDefinitionsFromDB($tableName)
	{
		$defs = [];

		// ###DATABASE###

		if (self::isMSSQL()){ // SQL Server

			// pegando colunas e PK
			$rows = DB::select("
				SELECT ORDINAL_POSITION, COLUMN_NAME, DATA_TYPE
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_NAME = '".$tableName."';
			"); // CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
			foreach ($rows as $row){
				// pegando PK
				if (!isset($defs["pk"]))
					$defs["pk"] = $row->COLUMN_NAME;
				$defs["fields"][$row->COLUMN_NAME]["type"] = $this->handleFieldType($row->DATA_TYPE);
			}

			// pegando relations - campos desta tabela que fazem referência ao ID de uma outra tabela (tabela estrangeira + fk nesta tabela que é o ID da tabela estrangeira)
			$rows = DB::select("
				SELECT
					object_name(f.referenced_object_id) RefTableName,
					COL_NAME(fc.parent_object_id,fc.parent_column_id) ColName
				FROM sys.foreign_keys f
				INNER JOIN
					sys.foreign_key_columns AS fc
					ON (f.OBJECT_ID = fc.constraint_object_id)
				WHERE f.parent_object_id = object_id('".$tableName."')
			"); // name, object_name(f.parent_object_id) ParentTableName,
			foreach ($rows as $row){
				$defs["relations"][$row->RefTableName]["fk"] = $row->ColName;
			}
			
		} // SQL Server

		if (self::isMySQL()){ // MySQL

			// pegando colunas e PK
			$rows = DB::select("show fields from ".$tableName);
			foreach($rows as $row){
				// pegando PK
				if ($row->Key == "PRI")
					$defs["pk"] = $row->Field;
				$defs["fields"][$row->Field]["type"] = $this->handleFieldType($row->Type);
			}
			
			// pegando relations - campos desta tabela que fazem referência ao ID de uma outra tabela (tabela estrangeira + fk nesta tabela que é o ID da tabela estrangeira)
			$sql = "
				select
				COLUMN_NAME as field,
				REFERENCED_TABLE_NAME as foreign_table,
				REFERENCED_COLUMN_NAME as foreign_key
				from
				INFORMATION_SCHEMA.KEY_COLUMN_USAGE
				where
				(TABLE_SCHEMA = SCHEMA()) AND
				(TABLE_NAME = '".$tableName."') AND
				(REFERENCED_TABLE_NAME IS NOT NULL)
			";
			$rows = DB::select($sql);
			foreach ($rows as $row){
				$defs["relations"][$row->foreign_table]["fk"] = $row->field;
			}

		} // MySQL

		return $defs;
	}

	/**
	 * Retorna definições de uma tabela no BD.
	 * @param string $tableName
	 * @return array
	 */
	private function tableDefinitions($tableName)
	{
		if (isset($this->dbDefinitions[$tableName]))
			return $this->dbDefinitions[$tableName];

		$tableDefinitions = $this->fetchTableDefinitionsFromDB($tableName);
		$this->dbDefinitions[$tableName] = $tableDefinitions;

		return $tableDefinitions;
	}

	/**
	 *
	 * @param type $fieldname
	 * @return boolean
	 */
	private function usingOriginalFieldName(&$fieldname)
	{
		if (substr($fieldname, 0, 1) == "*") {
			$fieldname = substr($fieldname, 1);
			return true;
		}

		return false;
	}

	/**
	 * Verifica existência de grafia no termo, elimina a grafia, retorna a grafia e ainda devolve por referência o termo sem a grafia.
	 * @param string $fieldNameRaw
	 * @return string
	 */
	private function verifySpelling(&$term){

		$original = $this->usingOriginalFieldName($term);

		// verifica igualdade "="
		if (substr($term, 0, 1) == "=") {
			$term = substr($term, 1);
			$originalTmp = $this->usingOriginalFieldName($term);
			return ["grafia" => 1, "original" => ($original ?? $originalTmp)];
		}

		// verifica in
		if (substr($term, 0, 1) == "[") {
			if (substr($term, 0, 2) == "[]"){
				$term = substr($term, 2);
				$originalTmp = $this->usingOriginalFieldName($term);
				return ["grafia" => 2, "original" => ($original ?? $originalTmp)];
			}
			if (substr($term, -1, 1) == "]")
				$term = substr($term, -1, 1);

			$term = substr($term, 1);
			$originalTmp = $this->usingOriginalFieldName($term);
			return ["grafia" => 2, "original" => ($original ?? $originalTmp)];
		}

		// verifica between
		if (substr($term, 0, 1) == "{") {
			if (substr($term, 0, 2) == "{}"){
				$term = substr($term, 2);
				$originalTmp = $this->usingOriginalFieldName($term);
				return ["grafia" => 3, "original" => ($original ?? $originalTmp)];
			}
			if (substr($term, -1, 1) == "}")
				$term = substr($term, -1, 1);

			$term = substr($term, 1);
			$originalTmp = $this->usingOriginalFieldName($term);
			return ["grafia" => 3, "original" => ($original ?? $originalTmp)];
		}

		$originalTmp = $this->usingOriginalFieldName($term);
		return ["grafia" => 0, "original" => ($original ?? $originalTmp)]; // sem grafia
	}

	/**
	 * Verifica se campo é CalcField
	 * @param string $fieldname
	 * @return boolean
	 */
	private function isCalcField($fieldname)
	{
		$lista = $this->params["calcFields"] ?? [];
		if (isset($lista[$fieldname]))
			return true;

		$parts = explode(".", $fieldname);
		if (count($parts) == 1) return false;

		return isset($lista[$parts[1]]) ? true:false;
	}

	/**
	 * Verifica se campo é TemplateField
	 * @param string $fieldname
	 * @return boolean
	 */
	private function isTemplateField($fieldname)
	{
		$lista = $this->params["templateFields"] ?? [];
		if (isset($lista[$fieldname]))
			return true;

		$parts = explode(".", $fieldname);
		if (count($parts) == 1) return false;

		return isset($lista[$parts[1]]) ? true:false;
	}

	/**
	 * Retorna lista de campos contidos no template.
	 * @param string $fieldNameFull
	 * @return array
	 */
	private function getFieldsFromTemplate($fieldNameFull)
	{
		$template = $this->params["templateFields"][$fieldNameFull] ?? null;

		if (empty($template)){
			$parts = explode(".", $fieldNameFull);
			$template = $this->params["templateFields"][$parts[1]];
		}

		$matches = [];
		$result = [];
		$control = [];
		preg_match_all ("/\[\[([a-z0-9\._-]+)\]\]/mi", $template, $matches);
		$matches = $this->invertMatches($matches);
		foreach ($matches as $item){
			//$result[] = Pipe::take($item[1], "substr")->pipe(1)->pipe(0, -1)->get();
			if (!empty($control[$item[1]])) continue;
			$control[$item[1]] = true;
			$result[] = $item[1];
		}
		return $result;
	}

	/**
	 * Retorna nome puro de campo e tabela a qual pertence.
	 * @param string $fieldNameFull
	 * @return array[2]
	 */
	private function getPureFieldAndTable ($fieldNameFull)
	{
		$fieldNameParts = explode(".", $fieldNameFull);
		if (count($fieldNameParts) > 1) {
			$tn = $fieldNameParts[0];
			$fn = $fieldNameParts[1];
		}
		else {
			$tn = $this->modelTableName;
			$fn = $fieldNameParts[0];
		}

		return [$fn, $tn];
	}

	/**
	 * Processa template de campos e retorna resultado.
	 * @param type $row
	 * @param type $fieldNameRaw
	 * @return string
	 */
	private function processFieldTemplate($row, $fieldNameFull)
	{
		$template = $this->params["templateFields"][$fieldNameFull] ?? null;
		if (empty($template)){
			$parts = explode(".", $fieldNameFull);
			$template = $this->params["templateFields"][$parts[1]];
		}

		$fields = $this->getFieldsFromTemplate($fieldNameFull);

		foreach ($fields as $field){
			list($fieldname) = $this->getPureFieldAndTable ($field);
			$template = str_ireplace ("[[".$field."]]", $row->$fieldname, $template);
		}

		return $template;
	}

	/**
	 * Verifica que tipo de valor é $v e trata adequadamente
	 * @param mixed $v
	 * @return string
	 */
	private function vTransform($v)
	{
		// se é numérico
		if (is_numeric($v))
			return $v;

		// se é nome de campo (encapsulado com [])
		if ((substr($v, 0, 1) == "[") && (substr($v, -1) == "]"))
			return $v;

		// se é nulo
		if (strtolower($v) == "null")
			return "null";

		// se não, é string. Colocar aspas.
		return "'".$v."'";
	}

	/**
	 * Calcula e/ou retorna expressão CALC de acordo com a situação
	 * @param type $fieldNameFull
	 * @return string
	 */
	private function getCalcExpression($fieldNameFull)
	{
		$exp = $this->params["calcFields"][$fieldNameFull] ?? null;

		if (empty($exp)){
			$parts = explode(".", $fieldNameFull);
			$exp = $this->params["calcFields"][$parts[1]];
		}

		// ###DATABASE###

		if (self::isMSSQL() || self::isMySQL()) // SQL Server e MySQL (MySQL aceita uma outra sintaxe, mas essa serve pra esse caso)
			if (is_array($exp)){
				$temp = "case ".$fieldNameFull." ";
				foreach ($exp as $vo => $vc)
					if (strtolower($vo) != "else")
						$temp .= "when ".$this->vTransform($vo)." then ".$this->vTransform($vc)." ";
					else
						$temp .= "else ".$this->vTransform($vc)." ";
				$temp .= "end";
				$exp = $temp;
			}

		return $exp;
	}

	/**
	 * Aplica filtro na coluna
	 * @param type $query
	 * @param type $search
	 * @param type $fieldNameRaw
	 * @param type $source
	 * @return void
	 */
	private function applyColumnFilter($query, $search, $fieldNameRaw, $source)
	{
		// misc
		switch ($source) {
			case 1:
				// source = 1 (filtro geral) não considera a Grafia
				$clause = "orWhere";
				$this->verifySpelling($fieldNameRaw); // só para limpar o nome do campo, caso haja Grafia.
				$grafia = 0;
				$original = false;
			break;
			case 2:
			case 3:
				$clause = "where";
				$spelling = $this->verifySpelling($fieldNameRaw);
				$grafia = $spelling["grafia"];
				$original = $spelling["original"];

				$spellingVal = $this->verifySpelling($search);
				$grafiaVal = $spellingVal["grafia"];
				//$originalVal = $spellingVal["original"]; // Original pode ser definido apenas no field. Nunca no value

				if ($grafiaVal) $grafia = $grafiaVal;
			break;
		}

		$rawClause = $clause."Raw";

		// nome completo (tabela.campo)
		$fieldNameFull = $this->extractFieldName($fieldNameRaw);

		// se é campo template, não filtra.
		if ($this->isTemplateField($fieldNameFull)) return;

		// separando nome completo
		list($fieldname, $tablename) = $this->getPureFieldAndTable ($fieldNameFull);

		// aplicando join
		$this->applyJoins($query, $fieldNameRaw);

		// definindo fieldtype para a rotina
		if ($this->isCalcField($fieldNameFull))
			$fieldtype = "calc";
		else
			$fieldtype = $this->tableDefinitions($tablename)["fields"][$fieldname]["type"];

		// executando filtragem segundo tipo
		switch($fieldtype){
			// {***} tratar datetime separadamente de date, de forma a considerar a hora também.
			case "datetime":
			case "date":
				switch ($grafia){
					case 1: // igualdade
						// ###DATABASE###
						if (self::isMSSQL() || self::isMySQL())
							$query->$rawClause(self::dateFormatFn($fieldNameFull)." = '".$search."'"); // SQL Server e MySQL
					break;
					case 2: // in
						// ###DATABASE###
						if (self::isMSSQL() || self::isMySQL())
							$query->$rawClause(self::dateFormatFn($fieldNameFull)." in (".$search.")"); // SQL Server e MySQL
					break;
					case 3: // between
						// {***} Permitir outros formatos de data
						$inItems = explode (",", $search);
						$dt_ini = Carbon::createFromFormat('d/m/Y', $inItems[0]);
						$dt_fim = Carbon::createFromFormat('d/m/Y', $inItems[1]);
						$query->whereBetween($fieldNameFull, [$dt_ini, $dt_fim]);
					break;
					default:
						// ###DATABASE###
						if (self::isMSSQL() || self::isMySQL())
							$query->$rawClause(self::dateFormatFn($fieldNameFull)." like '%".$search."%'"); // SQL Server e MySQL
				}
			break;
			case "time":
			case "varchar":
				switch ($grafia){
					case 1: // igualdade
						$query->$clause($fieldNameFull, $search);
					break;
					case 2: // in
						// não se aplica
					break;
					case 3: // between
						// não se aplica
					break;
					default:
						$query->$clause($fieldNameFull, "like", "%".$search."%");
				}
			break;
			case "calc":
				switch ($grafia){
					case 1: // igualdade
						if ($original)
							$query->$rawClause($fieldNameFull." = '".$search."'");
						else
							$query->$rawClause("(".$this->getCalcExpression($fieldNameFull).") = '".$search."'");
					break;
					case 2: // in
						if ($original)
							$query->$rawClause($fieldNameFull." in (".$search.")");
						else
							$query->$rawClause("(".$this->getCalcExpression($fieldNameFull).") in (".$search.")");
					break;
					case 3: // between
						$inItems = explode (",", $search);
						if ($original)
							$query->whereBetween($fieldNameFull, [$inItems[0], $inItems[1]]);
						else
							$query->whereBetween($this->getCalcExpression($fieldNameFull), [$inItems[0], $inItems[1]]);
					break;
					default:
						if ($original)
							$query->$rawClause($fieldNameFull." like '%".$search."%'");
						else
							$query->$rawClause("(".$this->getCalcExpression($fieldNameFull).") like '%".$search."%'");
				}
			break;
			default:
				switch ($grafia){
					case 2: // in
						$query->$rawClause($fieldNameFull." in (".$search.")");
					break;
					case 3: // between
						$inItems = explode (",", $search);
						$query->whereBetween($fieldNameFull, [$inItems[0], $inItems[1]]);
					break;
					case 1: // igualdade
					default:
						$query->$clause($fieldNameFull, $search);
				}
				// {***} Casos não cobertos:
				// 3. cpf (campo com tratamento especial
				//		ex: $mainQuery->where("dba_cpf", "like", "%".str_replace([".", "-", "/"], "", $extra["cpf"])."%");
		}
	}

	/**
	 * Aplica 'order by' nas colunas, conforme invocação.
	 * @param type $query
	 * @param type $fieldNameRaw
	 * @param type $sense
	 * @return void
	 */
	private function applyColumnOrder($query, $fieldNameRaw, $sense)
	{
		$spelling = $this->verifySpelling($fieldNameRaw);
		$original = $spelling["original"];

		// nome completo (tabela.campo)
		$fieldNameFull = $this->extractFieldName($fieldNameRaw);

		// se é campo template, não ordena.
		if ($this->isTemplateField($fieldNameFull)) return;

		// É campo calculado?
		if ($this->isCalcField($fieldNameFull) && !$original)
			$query->orderBy(DB::raw($this->getCalcExpression($fieldNameFull)), $sense);
		else
			$query->orderBy($fieldNameFull, $sense);
	}

	/**
	 * Retorna nome temp para as colunas a ser usado no select.
	 * @param int $idx
	 * @return string
	 */
	private function genColName($idx)
	{
		return "col".$idx;
	}

	/**
	 * Aplica 'select' para colunas provenientes de templates.
	 * @param type $query
	 * @param type $fieldname
	 * @return void
	 */
	private function selectTemplateField($query, $fieldname)
	{
		// Motivo: por ser o único tipo de coluna (template field) que é colocado no SQL sem alias,
		// ele dá erro no banco em caso de repetição de colunas. O uso deste método elimina essa repetição.

		if (!empty($this->selectedTemplateFields[$fieldname])) return;

		$this->selectedTemplateFields[$fieldname] = true;
		$query->addSelect($fieldname);
	}

	/**
	 * Aplica 'select' das colunas.
	 * @param type $query
	 * @param type $fieldNameRaw
	 * @param type $idx
	 * @return void
	 */
	private function applyColumnSelect($query, $fieldNameRaw, $idx)
	{
		$spelling = $this->verifySpelling($fieldNameRaw);
		$original = $spelling["original"];

		// aplicando join
		$this->applyJoins($query, $fieldNameRaw);

		// nome completo (tabela.campo)
		$fieldNameFull = $this->extractFieldName($fieldNameRaw);

		// nome do campo no select
		$selectname = $this->genColName($idx);

		// separando nome completo
		list($fieldname, $tablename) = $this->getPureFieldAndTable ($fieldNameFull);

		// se é campo template, tratamento diferente.
		if ($this->isTemplateField($fieldNameFull)) {
			$lista = $this->getFieldsFromTemplate($fieldNameFull);
			foreach ($lista as $tempFieldFull)
				$this->selectTemplateField($query, $tempFieldFull);
			return;
		}

		// É campo calculado?
		if ($this->isCalcField($fieldNameFull) && !$original){
			// ###DATABASE###
			if (self::isMSSQL())
				$query->addSelect(DB::raw($selectname." = ".$this->getCalcExpression($fieldNameFull))); // SQL Server
			if (self::isMySQL())
				$query->addSelect(DB::raw($this->getCalcExpression($fieldNameFull)." as ".$selectname)); // MySQL
			return;
		}

		// se for data...
		# definindo fieldtype para a rotina
		$fieldtype = $this->tableDefinitions($tablename)["fields"][$fieldname]["type"];
		#
		if ($fieldtype == "datetime" || $fieldtype == "date"){
			// ###DATABASE###
			if (self::isMSSQL()) $query->addSelect(DB::raw($selectname." = ".self::dateFormatFn($fieldNameFull))); // SQL Server
			if (self::isMySQL()) $query->addSelect(DB::raw(self::dateFormatFn($fieldNameFull)." as ".$selectname)); // MySQL
			return;
		}

		// tratamento padrão
		$query->addSelect($fieldNameFull." as ".$selectname);
	}

	/**
	 * Retorna lista de colunas da tabela HTML, com definições de filtro, se houver.
	 * @return array
	 */
	private function getColumns()
	{
		$columnsRequested = $this->getRequestedColumns();
		$columnsParams = $this->params["columns"] ?? [];
		if (!is_array($columnsParams)) return $columnsRequested;

		foreach($columnsParams as $idx => $item)
			if (is_array($item))
				foreach($item as $propname => $value)
					$columnsRequested[$idx][$propname] = $value;
			else
				$columnsRequested[$idx]["name"] = $item;

		return $columnsRequested;
	}

	private static function getDatabaseType()
	{
		return config('database.default');
	}

	private static function isMySQL()
	{
		return self::getDatabaseType() == "mysql" ? TRUE:FALSE;
	}

	private static function isMSSQL()
	{
		return self::getDatabaseType() == "sqlsrv" ? TRUE:FALSE;
	}

	private static function isSQLite()
	{
		return self::getDatabaseType() == "sqlite" ? TRUE:FALSE;
	}

	private static function isPGSQL()
	{
		return self::getDatabaseType() == "pgsql" ? TRUE:FALSE;
	}

	private static function dateFormatFn($fieldname)
	{
		// {***} TODO flexibilizar formato da data. Hoje, está fixo em DD/MM/YYYY.
		if (self::isMSSQL()) return "convert(varchar(10), ".$fieldname.", 103)"; // SQL Server
		if (self::isMySQL()) return "DATE_FORMAT(".$fieldname.", '%d/%m/%Y')"; // MySQL
	}

	private function handleFieldType($fieldTypeRaw)
	{
		if (strpos($fieldTypeRaw, "char") !== FALSE) return "varchar";
		if (strpos($fieldTypeRaw, "text") !== FALSE) return "varchar";
		if (strpos($fieldTypeRaw, "blob") !== FALSE) return "varchar";
		if (strpos($fieldTypeRaw, "int") !== FALSE) return "int";
		if (strpos($fieldTypeRaw, "datetime") !== FALSE) return "datetime";
		if (strpos($fieldTypeRaw, "date") !== FALSE) return "date";
		if (strpos($fieldTypeRaw, "time") !== FALSE) return "time";
		if (strpos($fieldTypeRaw, "num") !== FALSE) return "decimal";
		if (strpos($fieldTypeRaw, "dec") !== FALSE) return "decimal";
		return $fieldTypeRaw;
	}

	public function __construct ($params = [])
	{
		$this->dbDefinitions = $this->getDatabaseDefinitions();
		//$this->params = $params ?? $this->getRequestedParams();
		$this->params = array_merge ($this->getRequestedParams(), $params);
		$this->modelClass = "\\App\\".$this->params["modelname"];
		$this->modelTableName = (new $this->modelClass)->getTable();
		$this->allJoins[] = $this->modelTableName;
		$this->modelTablePk = $this->tableDefinitions($this->modelTableName)["pk"];
	}

	/**
	 * Método estático que permite invocação sem a necessidade de instanciar a classe, para obter a query.
	 * @param array $params
	 * @return Collect
	 */
	public static function query($params = [])
	{
		$dte = new self($params);
		return $dte->getQuery();
	}

	/**
	 * Retorna a Query tratada.
	 * @return Collect
	 */
	public function getQuery()
	{
        // parametros vindos do datatable
		$order = $this->getRequestedOrder();
		$search = $this->getRequestedGeneralFilter();
		$columns = $this->getColumns();
		$extra = $this->getRequestedExtraFilters();
		$params = $this->params;

		// misc
		$modelClass = $this->modelClass;
		$modelTableName = $this->modelTableName;
		$modelTablePk = $this->modelTablePk;
		$fixedFilters = $params["fixedFilters"] ?? [];
		$fixedJoins = $params["fixedJoins"] ?? [];

        // iniciando query
        $mainQuery = $modelClass::query();

		if (!empty($params["distinct"]))
			$mainQuery->distinct();

		// aplicando joins fixos
		foreach ($fixedJoins as $lineJoin)
			$this->applyJoins($mainQuery, $lineJoin);
		
		// aplicando joins das colunas da tabela
		foreach ($columns as $item){
			$tmpcolname = isset($item["name"]) ? $item["name"] : $item["data"];
			$this->applyJoins($mainQuery, $tmpcolname);
		}

		// aplicando filtros fixos
		$this->processFilterArray($mainQuery, $fixedFilters);

        // contagem geral (populacao)
		$newQuery = clone $mainQuery;
		// ###DATABASE###
		if (self::isMSSQL() || self::isMySQL())
        	$totalizador_geral = $newQuery->selectRaw("count($modelTableName.$modelTablePk) as total")->first();
        $this->registros_count = $totalizador_geral->total;

        // filtrando busca geral
		if ($search) {
			$mainQuery->where(function($query) use ($search, $columns){
				foreach ($columns as $item){
					$tmpcolname = isset($item["name"]) ? $item["name"] : $item["data"];
					$this->applyColumnFilter($query, $search, $tmpcolname, 1);
				}
			});
		}

        // filtrando busca por coluna
		foreach ($columns as $item){
			$tmpcolname = isset($item["name"]) ? $item["name"] : $item["data"];
			if ($item["search"]["value"] != NULL)
				$this->applyColumnFilter($mainQuery, $item["search"]["value"], $tmpcolname, 2);
		}

		// filtros extras
		if ($extra) {
			foreach ($extra as $fieldNameRaw => $filterVale)
				if ($filterVale != NULL)
					$this->applyColumnFilter($mainQuery, $filterVale, $fieldNameRaw, 3);
		}

		// contando total filtrados
		$newQuery = clone $mainQuery;
		// ###DATABASE###
		if (self::isMSSQL() || self::isMySQL())
			$totalizador_filtrado = $newQuery->selectRaw("count($modelTableName.$modelTablePk) as total")->first();
        $this->registros_filtered_count = $totalizador_filtrado->total;

		// ordenando
		foreach ($order as $item){
			$colidx = isset($columns[$item["column"]]["name"]) ? $columns[$item["column"]]["name"] : $columns[$item["column"]]["data"];
			$this->applyColumnOrder($mainQuery, $colidx, $item["dir"]);
		}

		// colunas / campos
		foreach ($columns as $idx => $item){
			$colidx = isset($item["name"]) ? $item["name"] : $item["data"];
			$this->applyColumnSelect($mainQuery, $colidx, $idx);
		}

		// salvando definições do BD na session
		$this->saveDatabaseDefinitions();

		return $mainQuery;
	}

	/**
	 * Método estático que permite invocação sem a necessidade de instanciar a classe.
	 * @param array $params
	 * @return Collect
	 */
	public static function getResult($params = [])
	{
		$query = new self($params);
		return $query->getPage();
	}

	/**
	 * Método principal. Comanda toda a brincadeira.
	 * @return Collect
	 */
	public function getPage()
	{
		// misc
		$draw = $this->getRequestedDraw();
		$start = $this->getRequestedStart();
		$length = $this->getRequestedLength();
		$columns = $this->getColumns();
		$columnsRequested = $this->getRequestedColumns();

		// pegando página específica
		$pageQuery = $this->getQuery()->offset($start)->limit($length);
		$registros = $pageQuery->get();

        // preparando dados para o retorno
		$dados = [];
        foreach ($registros as $row) {
            $temp = [];

			foreach ($columnsRequested as $idx => $col){
				$colname = $this->genColName($idx);
				$fieldNameRaw = isset($columns[$idx]["name"]) ? $columns[$idx]["name"] : $columns[$idx]["data"];
				$fieldNameFull = $this->extractFieldName($fieldNameRaw);
				//$colidx = isset($col["name"]) ? $col["name"] : $col["data"];
				$colidx = $col["data"];

				if ($this->isTemplateField($fieldNameFull))
					$temp[$colidx] = $this->processFieldTemplate($row, $fieldNameFull);
				else
					$temp[$colidx] = $row->$colname;
			}

            $dados[] = $temp;
        }

		$return = [
            "draw" => $draw,
            "recordsTotal" => $this->registros_count,
            "recordsFiltered" => $this->registros_filtered_count,
            "data" => $dados
        ];

		if (!empty($this->params["debug"]))
			$return["debugSQL"] = str_replace_array("?", $pageQuery->getBindings(), $pageQuery->toSql());

        // retornando
        return $return;
	}
}
