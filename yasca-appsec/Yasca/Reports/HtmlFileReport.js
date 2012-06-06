"use strict";
var Yasca = new function(){
	var self = this;
	
	var severities = ['','Critical','High','Medium','Low','Info'];
	var sorting = {
		initial: function (a,b) {
			if (a.severity > b.severity) { return 1; }
			if (a.severity < b.severity) { return -1; }
			if (a.pluginName > b.pluginName) { return 1; }
			if (a.pluginName < b.pluginName) { return -1; }
			if (a.filename > b.filename) { return 1; }
			if (a.filename < b.filename) { return -1; }
			if (a.lineNumber > b.lineNumber) { return 1; }
			if (a.lineNumber < b.lineNumber) { return -1; } 
			return 0;
		}
	};
	
	self.saveJsonReport = (function(){
		
		if (  !(window.BlobBuilder 	  ||
				window.WebKitBlobBuilder ||
				window.MozBlobBuilder 	  ||
				window.MSBlobBuilder
			   ) || 
				
			   (	
					!((window.URL || window.webkitURL || {}).createObjectURL) && 
					!window.saveAs
				) || 
				
				!window.JSON || !window.JSON.stringify){
			
			var features = [];
			if (!(window.BlobBuilder 	  ||
				  window.WebKitBlobBuilder ||
				  window.MozBlobBuilder 	  ||
				  window.MSBlobBuilder)){
				features.push('File API');
			}
			if (!((window.URL || window.webkitURL || {}).createObjectURL)){
				features.push('File URL API');
			}
			if (!window.saveAs){
				features.push('Filesaver API');
			}
			if (!window.JSON || !window.JSON.stringify){
				features.push('JSON API');
			}
			var message = 
			  'These standard features are required, but missing:\n' +
			  '    ' + features.join(', ') + '\n\n' +
			  'Please open this report in a different browser, such as:\n' +
			  '    Internet Explorer 10 or newer\n' +
			  '    Firefox 9 or newer\n' +
			  '    Google Chrome';
			
			return function(){ alert(message);};
		} else {
			var saveAs =
				window.saveAs	  ||
				function(blob, filename){
					alert('Please save (or rename) the result as a .json file.');
					window.open((window.URL || window.webkitURL).createObjectURL(blob), filename);
				};
			return function(){
				var bb = new (window.BlobBuilder 	  ||
						window.WebKitBlobBuilder ||
						window.MozBlobBuilder 	  ||
						window.MSBlobBuilder)();
				bb.append(JSON.stringify(Yasca.results));
				saveAs(bb.getBlob('application/x-json'), 'results.json');
			};
		}
	}());
	
	self.createReport = function () {
		var reportContainer = $('<div>');
		var resultsId = "resultsTable";
		var table = 
			$('<table cellpadding="0" border="0" cellspacing="2" style="display: none;">').attr(
				"id", resultsId
			).append(
				$('<thead>').append(
				    $('<th>').text('#').css({'width' : 0}),
					$('<th>').text('Severity').css({'width' : 0}),
					$('<th>').text('Plugin').css({'width' : 0}),
					$('<th>').text('Category').css({'width' : 0}),
					$('<th>').text('Message'),
					$('<th>').text('Details').css({'width' : 0})
				)
			);
		//Capture scroll position pointer in event handlers
		var scrollPos = 0;
		$.each(self.results, function(i, result) {
			var detailsId = "d" + i;
			reportContainer.append(
				$('<div class="detailsPanel" style="display:none;">').attr(
					'id', detailsId
				).append(
					$('<a href="#">Back to results</a>').click(function(){
						$('#' + detailsId).fadeOut('fast', function(){
							$('#' + resultsId).fadeIn('fast', function () {
								$(window).scrollTop(scrollPos);
							});
						});
					}),
					(function(){
						var retval = $('<p>').text(result.description);
						retval.html(retval.html().replace(/\r?\n/g, "<br/>"));
						return retval;
					}()),
					$('<ul class="references">').append(
						$.map(result.references || [], function(value, key){
							return $('<li>').append(
								$('<a target="_blank">').attr('href', key).text(value)
							).get();
						})
					),
					$('<ul class="unsafeData">').append(
						$.map(result.unsafeData || [], function(value, key){
							return $('<li>').append(
								document.createTextNode(key + ": "),
								document.createTextNode(value)
							).get();
						})
					),
					(function(){
						if (typeof result.filename != 'undefined'){
							var retval = result.filename;
							var ln = result.lineNumber || ''
							if (ln !== ''){
								retval += ":" + ln;
							}
							return $('<p>').text('File: ' + retval);
						} else {
							return document.createTextNode('');
						}
					}()),
					(function(){
						var any = false;
						var retval = $('<ul class="sourceCode">').append(
							$.map(result.unsafeSourceCode || [], function(value, key){
								any = true;
								return $('<li>').append(
									document.createTextNode(key + ": "),
									document.createTextNode(value)
								).get();
							})
						);
						if (any){
							return retval;
						} else {
							return document.createTextNode('');
						}
					}())
				)
			);
			
			table.append(
				$('<tr>').attr(
					'severity', result.severity
				).attr('id', i
				).append(
					$('<td>').text(i + 1),
					$('<td>').text(severities[result.severity]),
					$('<td>').text(result.pluginName),
					$('<td>').text(result.category),
					$('<td class="ellipsis">').text(
						(function(){
							var maxFilenameLength = 12;
							var retval = '';
							var fn = result.filename || '';
							if (fn !== ''){
								if (fn.length > maxFilenameLength){
									retval = '...' + fn.slice(3-maxFilenameLength);
								} else {
									retval = fn;
								}
								var ln = result.lineNumber || '';
								if (ln !== ''){
									retval += ':' + ln;
								}
								retval += ' - ';
							}
							retval += result.message;
							return retval;
						}())
					),
					$('<td>').append($('<a href="#">Details</a>').click(function(){
						scrollPos = $(window).scrollTop();
						$("#" + resultsId).fadeOut('fast', function(){
							$("#" + detailsId).fadeIn('fast');
						});
					}))
				)
			);
		});
		reportContainer.append(table);
		return reportContainer;
	};
	
	self.onReady = function(){
		
		$('table.header tr:first').append(
			$('<td>').append(
				$('<input type="button" value="Save JSON Report">').click(self.saveJsonReport)
			)
		);
		
		self.results.sort(sorting.initial);
		$('body').append(
			self.createReport()
		);
		
		$('#loading').remove();
		var table = $('#resultsTable');
		table.fadeIn('fast', function(){
			table.find('th').not(':contains("Message")').each(function(){
				var self = $(this);
				self.css({'width': self.width()});
			});
			table.css({'table-layout': 'fixed'});
		});
	};
};
$(document).ready(Yasca.onReady);