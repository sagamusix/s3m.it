  var votes = {};
  var numEntries;
  function addvote() {
  	var v = $('#voter').val();
  	if(!v) { alert("who voted?!"); return; }
  	votes[v] = $('#votesorter').sortable('toArray');
  	for(var k in votes[v]) { votes[v][k] = votes[v][k].replace("vote_", ""); }
    update();
  	$('#voter').val('');
  	$('#paster').val('');
  	for(var i=0;i<numEntries;i++) {
  		if(v == $('#author_' + (i+1)).text()) {
  			$('#penalty_' + (i+1)).removeProp('checked');
  		}
  	}
  }
  function update() {
  	$('#matrix tr').html('');
  	for(var k in votes) {
		  $('#matrix_headers').append("<td><em>" + k + "</em></td>");
		  for(var i in votes[k]) {
		  	var entryno = parseInt(votes[k][i]);
		  	var filename = $('#filename_' + entryno).text();
		  	$('#matrix_row_' + (parseInt(i)+1)).append("<td><small>" + filename + "</small></td>");
		  }
	}
  }
  function pasted() {
  	var irctexts = $('#paster').val();
  	var voter = "";
  	var order = {};
  	var m;
  	if(m = irctexts.match(/<(.*?)>/)) {
		voter = m[1];
	}
	irctexts = " " + irctexts.replace(/<.*?>/, "").toLowerCase();
	for(var i=0;i<numEntries;i++) {
		// skip finding voter's song
		var author = $('#author_' + (i+1)).text().toLowerCase();
		var filename = $('#filename_' + (i+1)).text().toLowerCase();
		var title = $('#title_' + (i+1)).text().toLowerCase();
		if(author == voter.toLowerCase()) continue;
		var lcs = strlcs(irctexts, " " + filename + "%% " + title);
		if(lcs.len > 3 || (filename.length < 3 && lcs.len > 1 && (lcs.start == 0 || irctexts[lcs.start] == ' '))) {
			order[ lcs.start - lcs.len/Math.max(filename.length, title.length) ] = i;
		}

	}
	var keys = [];
	for(var k in order) {
		keys.push(k);
	}
	keys.sort(function(a,b){ return parseFloat(a) - parseFloat(b); });
	var ret = [];
	for(k in keys) {
		ret.push(order[ keys[k] ]);
	}

	for(var k=ret.length;k>0;k--) {
		var entryno = ret[k-1] + 1;
		var entry = $('#vote_' + entryno);
		entry.remove();
		entry.prependTo('#votesorter');
		entry.hide().fadeIn(1000);
	}
	if(voter) $('#voter').val(voter);
	$('#votesorter').sortable('refresh');
  }

function strlcs(string1, string2){
	// init max value
	var ret = {start:0, len:0};
	// init 2D array with 0
	var table = Array(string1.length);
	for(a = 0; a <= string1.length; a++){
		table[a] = Array(string2.length);
		for(b = 0; b <= string2.length; b++){
			table[a][b] = 0;
		}
	}
	// fill table
	for(var i = 0; i < string1.length; i++){
		for(var j = 0; j < string2.length; j++){
			if(string1[i]==string2[j]){
				if(table[i][j] == 0){
					table[i+1][j+1] = 1;
				} else {
					table[i+1][j+1] = table[i][j] + 1;
				}
				if(table[i+1][j+1] > ret.len){
					ret.len = table[i+1][j+1];
					ret.start = i - table[i+1][j+1] + 1;
				}
			} else {
				table[i+1][j+1] = 0;
			}
		}
	}
	return ret;
}

    function sanitizeName(name)
    {
        name = encodeURIComponent(name);
        name = name.replace(/%08/g,'');    // Some IRC client apparently puts 0x08 chars around nicks
        return name.replace(/~/g,'%7E').replace(/%20/g,'+');    // Match PHP's urlencode()
    }

	function results() {
		var needVotes = [];
		var penalty = [];
		for(var i=0;i<numEntries;i++) {
			if($('#penalty_' + (i+1)).prop('checked')) {
				needVotes.push($('#author_' + (i+1)).text());
				penalty.push(i+1);
			}
		}
		if(needVotes.length) {
			if(!confirm("The following jerks will be penalized: " + needVotes + " OK?"))
				return;
		}

		var voters = [];
		var qstringobj = {};
		for(var v in votes) { voters.push(sanitizeName(v)); qstringobj['voter_' + sanitizeName(v)] = votes[v]; }
		qstringobj['votes'] = voters;
		qstringobj['penalty'] = penalty;

		var qstring = $.param(qstringobj);
		$.post(window.location.href, qstring, function(data) {
            if(data.match( /RESULTS!/)) {
                    window.location = BASEDIR + 'results/' + window.location.href.split('/').reverse()[0] + '.txt';
            }
            else {
                    alert(data);
                    console.log(data);
            }
        });

	}

  $(document).ready(function() {
  	numEntries = $('#matrix tr').length - 1;
  	$('#votesorter').sortable();
  	$('#votebutton').button().click(addvote);
  	$('#resultsbutton').button().click(results);

  	$('#paster').bind('paste', function(){ setTimeout(pasted, 10); });
  });
