
function removerAcentos( newStringComAcento ) {
    var string = newStringComAcento;
    var mapaAcentosHex = {
        a : /[\xE0-\xE6]/g,
        A : /[\xC0-\xC6]/g,
        e : /[\xE8-\xEB]/g,
        E : /[\xC8-\xCB]/g,
        i : /[\xEC-\xEF]/g,
        I : /[\xCC-\xCF]/g,
        o : /[\xF2-\xF6]/g,
        O : /[\xD2-\xD6]/g,
        u : /[\xF9-\xFC]/g,
        U : /[\xD9-\xDC]/g,
        c : /\xE7/g,
        C : /\xC7/g,
        n : /\xF1/g,
        N : /\xD1/g
    };
    for ( var letra in mapaAcentosHex ) {
        var expressaoRegular = mapaAcentosHex[letra];
        string = string.replace( expressaoRegular, letra );
    }
    return string;
}

function OrdenaJson(lista, chave, ordem) {
    return lista.sort(function(a, b) {
        var x = removerAcentos(a[chave]);
        var y = removerAcentos(b[chave]);
        if (ordem === 'ASC' ) { return ((x < y) ? -1 : ((x > y) ? 1 : 0)); }
        if (ordem === 'DESC') { return ((x > y) ? -1 : ((x < y) ? 1 : 0)); }
    });
}

if (!$.isFunction(replaceAll)) {
	function replaceAll(mystring, token, newtoken) {
		var tmpstring = new String(mystring);
		while (tmpstring.indexOf(token) != -1) {
			tmpstring = tmpstring.replace(token, newtoken);
		}
		return tmpstring;
	}
}


// Plugin
$.fn.downloadFromIt = function () {

	var url;
	var _this = this;
	var options = {}; // ainda não está sendo usado para nada. Em breve, pode ser que esse plugin receba options.

	for (var i in arguments){
		var arg = arguments[i];
		var type = $.type(arg);

		switch (type) {
			case "string":
				url = arg;
			break;
			case "object":
				options = arg;
			break;
		}
	}

	var data = $(_this).data("_dteData");
	var dataEncoded = $.param(data);
	url = url || "/datatablesEasy";

	window.location = url + "?" + dataEncoded;
};

// alias para versao local
$.fn.datatableEasyLocal = function (options) {
	return $(this).dataTableExtra("", options);
};

// Plugin
$.fn.datatablesEasy = function () {

	var url;
	var _this = this;
	var options = {};
	var remoteoptions = {};
	var extra = {};

	for (var i in arguments){
		var arg = arguments[i];
		var type = $.type(arg);

		switch (type) {
			case "string":
				url = arg;
			break;
			case "object":
				options = arg;
			break;
		}
	}

	// manter filtro geral
	if (options.keepFilter) {
		var keepFilter = options.keepFilter;
		delete options.keepFilter;
	}
	else {
		var keepFilter = true;
	}

	// manter filtro geral
	if (options.columnFilter) {
		var columnFilter = options.columnFilter;
		delete options.columnFilter;
	}
	else {
		var columnFilter = false;
	}

	// qualquer option pode ser passado por parametro data.
	var tblData = $(_this).data();
	options = $.extend({}, tblData, options);

	// buscando metadados e options
	var colunas = options.columns;
	var calcFields = options.calcFields || [];
	var templateFields = options.templateFields || [];
	var datatablesOptions = options.datatablesOptions || {};


	// definição de colunas
	var columnsDefined = true;
	if (colunas === undefined) {
		columnsDefined = false;
		colunas = [];
	}
	else {
		// se columns veio, mas é um array de nomes de colunas, converter para o formato do Datatables
		if (!(colunas[0] && colunas[0].data)){
			var tmp = [];
			for (var i in colunas)
				tmp.push({"data": colunas[i]});
			colunas = tmp;
		}
	}

	///// buscando colunas da tabela que podem conter metadados relevantes
	var cols = $();
	// verificando linhas do cabeçalho
	var rows = $(_this).children("thead").children("tr");
	rows.each(function(i){
		cols = cols.length >= rows.eq(i).children().length ? cols : rows.eq(i).children();
	});

	// verificando primeira linha do tbody, caso não tenha cabeçalho
	if (cols.length === 0){
		cols = $(_this).children("tbody").children("tr:first-child").children();
	}

	// verificando colunas da tabela em busca de definições e metadados.
	cols.each(function(i, mycol){
		var orderable = $(mycol).attr("data-orderable");
		var calc = $(mycol).attr("data-calc");
		var template = $(mycol).attr("data-template");

		if (columnsDefined){
			var columnName = colunas[i].data;
		}
		else {
			colunas.push({"data": columnName});
			var columnName = $(mycol).attr("data-column-name") || ("col"+i);
		}

		if ((orderable !== undefined) && (colunas[i].orderable === undefined))
			colunas[i].orderable = orderable;

		if (calc && (!calcFields[columnName])) calcFields[columnName] = calc;
		if (template && (!templateFields[columnName])) templateFields[columnName] = template;
	});

	// organizando parametros extras
	options.calcFields = calcFields;
	options.templateFields = templateFields;

	// definindo: local, remoto ou rota-padrão?
	if (url !== "")
		remoteoptions = {
			"serverSide": true,
			"ajax": {
				url: (url || "/datatablesEasy"),
				type: 'POST',
				data: function(data) {
					data.extradata = extra;
					data.extraparams = options;
					var token = $("input[name='_token']").val();
					if (token) data._token = token; // {***} TODO Implementar outras formas de pegar o token.

					$(_this).data("_dteData", data); // guardando dados para utilizar com outros utilitários associados ao DTE
				},
				dataFilter: function(response) { //
					var json_response = JSON.parse(response);
					if (json_response.debugSQL) {
						var ident = $(_this).attr("id") ? "#"+$(_this).attr("id") : "";
						console.log("DatatablesEasy"+ident+": "+json_response.debugSQL);
					}
					return response;
				}
			},
			"columns": colunas
		};

	var defaultOptions = {
        "processing": true,
		///////////////////// Manter os filtros da página//////////////////////////////////////
		"stateSave": true,
		stateSaveCallback: function(settings,data) {
			data.extradata = extra;
			var indexname = 'DataTables_' + '/' + window.location.href + settings.sInstance;
			localStorage.setItem(indexname, JSON.stringify(data));
		},
		stateLoadCallback: function(settings) {

			var indexname = 'DataTables_' + '/' + window.location.href + settings.sInstance;
			var data = JSON.parse(localStorage.getItem(indexname));
			if (!data) return {};

			// columns
			var columns = data.columns;
			for (var i in columns) {
				var column = columns[i];
				var search = column.search.search;

				// busca elemento onde esse filtro foi definido anteriormente. Primeiramente, no TFOOT
				var element = $(_this).children("tfoot").children("tr").children().eq(i).find('input, select, textarea');

				// se elemento ainda não tiver sido encontrado, procura no THEAD
				if (element.length === 0) element = $(_this).children("thead").children("tr").children().eq(i).find('input, select, textarea');

				// se elemento ainda não tiver sido encontrado...
				if (element.length === 0) {
					// pegando elemento-filtro fora da tabela
					var filterTag = "[data-tblfilter='"+colunas[i].data+"']";
					element = $("input"+filterTag+", select"+filterTag+", textarea"+filterTag);

					// se houver varios elementos com mesmo 'data-tblfilter', pega o que tem a tabela atual assinalada.
					if (element.length > 1)
						element = element.filter("[data-tblparent='"+$(_this).attr("id")+"']");
				}

				// se elemento não existe na combo, assinala o primeiro (geralmente é um TODOS ou similar)
				if (element.is("select")){
					var option = element.find("option[value='"+search+"']");
					if (option.length === 0) {
						option = element.find("option:first-child");
						search = option.attr("value");
						data.columns[i].search.search = search;
					}
				}
				element.val(search);
			}

			// extra filters
			extra = data.extradata;
			if (extra && $.type(extra) === "object"){
				for (var fieldname in extra){
					var search = extra[fieldname];
					var filterTag = "[data-tblfilter='"+fieldname+"']";
					var element = $("input"+filterTag+", select"+filterTag+", textarea"+filterTag);

					// se houver varios elementos com mesmo 'data-tblfilter', pega o que tem a tabela atual assinalada.
					if (element.length > 1)
						element = element.filter("[data-tblparent='"+$(_this).attr("id")+"']");

					// se elemento não existe na combo, assinala o primeiro (geralmente é um TODOS ou similar)
					if (element.is("select")){
						var option = element.find("option[value='"+search+"']");
						if (option.length === 0) {
							option = element.find("option:first-child");
							search = option.attr("value");
						}
					}

					// atribui, finalmente
					element.val(search);
				}
			}

			// flags - tratamento inicial
			var flagstext = data.search.search;
			var flags = flagstext.split(",");
			for (var i in flags) {
				var nomepuro = replaceAll(replaceAll(flags[i],")",""),"(","");
				var target = $(_this).find(".btnFilterFlag[data-flag='"+nomepuro+"']");
				$(target).addClass("active");
				if (flags[i].substr(0, 1) === "(")
					$(target).toggleClass("unmarked none");
				else
					$(target).toggleClass("marked none");
			}

			return data;
		},
		////////////////////////////////////////////////////////////////////////////////////////
        "paging":true,
        //"order":[0, 'asc'],
        initComplete:function (){
			if (!columnFilter) return;
            var r = $(_this).find('tfoot tr');
            r.find('td, th').each(function(){
                $(this).css('vertical-align', 'middle');
            });
            $(_this).find('thead').append(r);
        },
		"pagingType" : "full_numbers" ,
		"language"   : {
			"url" : "/vendor/datatableseasy/datatablesEasy/datatables_ptbr.js"
		} ,
		"responsive" : true
    };
	var finaloptions = $.extend({}, defaultOptions, datatablesOptions, remoteoptions);

    var table = $(this).DataTable(finaloptions);

    // remove a caixa de busca que vem como padrão
	if (!keepFilter)
		$('.dataTables_filter label, .dataTables_filter input[type="search"]').remove();

    // pesquisa pelas colunas
    table.columns().every( function () {
        var that = this;
        $(this.footer()).find('input, select, textarea').on('keyup change',function(){
            if (that.search() !== this.value){
                that.search(this.value).draw();
            }
        });
    });

	var ptp = "[data-tblparent='"+$(_this).attr("id")+"']";
	$("button"+ptp+", input[type='button']"+ptp+"input[type='submit']"+ptp).attr("type", "button").on("click", function(){

		extra = {};
		var ft = "[data-tblfilter]";
		var myelems = $(this).closest("form").find("input"+ft+", select"+ft+", textarea"+ft);
		myelems.each(function(){

			var field = $(this).attr("data-tblfilter");
			var idxField = -1;
			for (var i in colunas) { // busncado indice da coluna correspondente
				if (colunas[i].data === field) {
					idxField = i;
					break;
				}
			}
			if (idxField >= 0) {
				table.column(idxField).search($(this).val());
			}
			else { // filtro extra
				extra[field] = $(this).val();
			}
		});

		table.draw();
	});

	/* código para, futuramente, poder atribuir on change nos filtros de fora da tabela
    table.columns().flatten().each( function (idxCol) {
		var column = table.column(idxCol);
        $('input, select, textarea', column.footer()).on('keyup change',function(){
            if (column.search() !== this.value){
                column.search(this.value).draw();
            }
        });
    });
	 */

    // limpar filtros
    $(_this).find("button[type='reset'], input[type='reset'], button.clearFilter, input[type='button'].clearFilter, input[type='submit'].clearFilter, a.clearFilter").on("click", function(e){
        e.preventDefault();
        table.columns().every( function () {
            this.search("");
        });
        table.search("").draw();

        $(_this).children("thead").find("input[type='text'], input[type='search'], select").val('');

		$(_this).find(".btnFilterFlag").removeClass("active marked unmarked").addClass("none").attr("title", "Marcados e desmarcados"); // flag
    });

    // flags - executar filtragem
    $(_this).find(".btnFilterFlag").on("click", function(){
        if ($(this).hasClass("marked")) $(this).toggleClass("marked unmarked").attr("title", "Somente desmarcados");
        else if ($(this).hasClass("unmarked")) $(this).toggleClass("unmarked active none").attr("title", "Marcados e desmarcados");
             else if ($(this).hasClass("none")) $(this).toggleClass("none marked active").attr("title", "Somente marcados");

        var flags = [];
        $(_this).find(".btnFilterFlag.active").each(function(){
            if ($(this).hasClass("marked"))
                flags.push($(this).attr("data-flag"));
            else
                flags.push("("+$(this).attr("data-flag")+")");
        });
        table.search(flags.join(",")).draw();
    });

	// prevendo ENTER
    $(_this).closest('form').on('keyup keypress', function(e){
        var keyCode = e.keyCode || e.wich;
        if(keyCode ===13){
            e.preventDefault();
            return false;
        }
    });

    return table;
};
