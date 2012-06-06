<?php
require_once("lib/common.php");
require_once("lib/Report.php");
require_once("lib/Result.php");
require_once("lib/Yasca.php");

/**
 * HTMLGroupReport Class
 *
 * This class renders scan results as rich HTML.
 * @author Michael V. Scovetta <scovetta@users.sourceforge.net>
 * @version 2.0
 * @license see doc/LICENSE
 * @package Yasca
 */
class HTMLGroupReport extends Report {
    /**
     * The default extension used for reports of this type.
     */
    public $default_extension = "html";

    /**
     * Executes an HTMLReport, with output going to $options['output']
     */ 
    public function execute() {
        if (!$handle = $this->create_output_handle()) return;
        
        fwrite($handle, $this->get_preamble());
        $row_id_format = "%0" . ceil(log10(count($this->results))) . "d";
        $num_results_written = 0;
        $current_category = "";
        $description_popup_list = array();

        $yasca =& Yasca::getInstance();
        $yasca->log_message("There were " . count($this->results) . " results found.", E_USER_NOTICE);

        foreach ($this->results as $result) {
            if (!$this->is_severity_sufficient($result->severity))
                continue;

            if ($yasca->options['source_required'] == true && !is_file($result->filename) && !is_readable($result->filename)) {
                continue;
            }

            $filename = $result->filename;
            $pinfo = pathinfo($filename);
            $ext = (isset($pinfo['extension']) ? $pinfo['extension'] : "");
            if (isset($result->custom['translation'])) {
                $t =& $result->custom['translation'];
                $filename = $t[basename($filename, ".$ext")];
            }
            $filename = correct_slashes($filename);

            $source_context = "";
            if (is_array($result->source_context)) {
                foreach ($result->source_context as $context) {
                    $context = preg_replace('/[\r\n]/', "", $context);
                    //$context = substr(trim($context), 0, 76);
                    $source_context .= htmlentities($context) . "~~~";
                }
            }
            $row_id = sprintf($row_id_format, ++$num_results_written);
            $category_link = $result->category_link;
            $category = $result->category;
            $plugin_name = $result->plugin_name;
            $source_code_class = ($result->is_source_code ? "code" : "");
            $source = $result->source;
            $plugin_id = md5($category . $plugin_name . $result->description);
            $severity_description = $this->get_severity_description($result->severity);
            $filename_base = basename($filename);
            $line_number = $result->line_number;
            $line_number_field = $result->line_number > 0 ? ":" . $result->line_number : "";
            $category_link_field = "<a href=\"$category_link\" target=\"_blank\">$category</a>";
            if ($category_link == "") $category_link_field = $category;
            $description = $result->description;
            $target_dir = $this->options['dir'];
            $proposed_fix = $result->proposed_fix;
            
            if ($category != $current_category) {
                fwrite($handle,
                    "<tr>" .
                    " <td colspan=\"3\" style=\"padding: 4px;font-weight: bold;\">" .
                                        "<a name=\"" . md5($category) . "\"></a>" .
                    "$category_link_field &nbsp;&nbsp;&nbsp;<i><a style=\"color:#888888;font-weight:normal;\" href=\"javascript:void(0);\" onclick=\"toggle_category(event)\">hide</a></i></td></tr>");
                $current_category = $category;
            } 
            
            fwrite($handle,
                    "<tr id=\"row_$row_id\">" .
                    " <td title=\"[Severity: $severity_description]\n [Plug-in: $plugin_name]\" class=\"severity_$severity_description\">$row_id</td>\n");
            
            fwrite($handle, "<td>"); 

            if ($result->is_source_code || ($line_number != 0 && strlen(trim($source_context)) > 0)) {
                fwrite($handle, 
                    "<div class=\"snippet_anchor\" title=\"View Source Code\" onclick=\"show_code_snippet(event, '$row_id', $line_number, 'file://$filename');\">&#x25A0;</div>" .
                    "<div id=\"c_$row_id\" class=\"nowrap\" style=\"display:none;\" onclick=\"cancel_event(event);\">$source_context</div>");
            }
            fwrite($handle, "\n<!-- $plugin_id \t $description -->\n");
            if ($description != "") {
                fwrite($handle, "<div class=\"description_anchor\" title=\"View Description\" onclick=\"show_description(event, '$plugin_id');\">&#x25A0;</div>");
                if (!isset($description_popup_list[$plugin_id])) {
                    fwrite($handle, "<div class=\"description_popup\" onclick=\"cancel_event(event);\" id=\"d_$plugin_id\" style=\"display:none;\"><h4>$plugin_name</h4>$description</div>");
                    $description_popup_list[$plugin_id] = true;
                }
            }
            
            if ($proposed_fix != "") {
                fwrite($handle, "<div class=\"proposed_fix_anchor\" title=\"View Proposed Fix\" onclick=\"show_fixed_snippet(event, '$row_id', $line_number, 'file://$filename', '". htmlentities(htmlentities(trim($proposed_fix))) ."');\">&#x25A0;</div>");
            }
            
            fwrite($handle, "<div class=\"ignore_finding_anchor\" title=\"Ignore Finding\" onclick=\"ignore('$row_id', '$filename', '$line_number', '$category');\">&#x25A0;</div>");

            
            fwrite($handle,     
                    "<a style=\"margin-right: 12px;\" source_code_link=\"true\" href=\"file://$filename\" target=\"_blank\" title=\"$filename\">$filename_base$line_number_field</a>" .
                    "</td>");
                
            fwrite($handle, "<td nowrap class=\"message $source_code_class\" title=\"" . htmlentities(trim($source)) . "\">" .
                            htmlentities( ($result->is_source_code ? ellipsize(trim($source), 85, "...", false) : trim($source)) ) . "</td>");
        }
        if ($num_results_written == 0) {
            fwrite($handle, "<tr><td colspan=\"4\" align=\"center\">No results were found.</td></tr>");
        }
        
        fwrite($handle, $this->get_postamble());
        
        /* Deprecated
        if ($this->use_digital_signature) {
            // Sign the file
            fseek($handle, 0);
            $html_content = "";
            while(!feof($handle)) {
                $html_content .= fread($handle, 4096);
            }
            $signature = Yasca::calculate_signature($html_content);
            fseek($handle, 0, SEEK_END);
            fwrite($handle, "<!-- SIGNATURE: [$signature] -->"); 
        }*/
        
        fclose($handle);
    }
    
    protected function get_preamble() {
        $generation_date = date('Y-m-d H:i:s');
        $version = constant("VERSION");
        $yasca =& Yasca::getInstance();
        $stylesheet_content = file_get_contents("resources/style.css");
        $base_dir = "." . DIRECTORY_SEPARATOR; 
        $attachment_list_select_box = $this->generate_attachment_select_box();
        $attachment_list = $this->generate_attachment_list();
        $attachment_list_style = $this->has_attachments() ? "" : "display:none;";
        
        return <<<END
        <html>
            <head>
                <title>Yasca v$version - Report</title>
                <style type="text/css">
                    $stylesheet_content
                </style>
                <script language="javascript">
                    var open_description = null;
                    var CRLF = String.fromCharCode(13) + String.fromCharCode(10);                   
                    
                    function on_body_load() {
                        fix_empty_cells();
                    }
                    
                    function fix_empty_cells() {
                        var td_list = document.getElementsByTagName('td');
                        for (var i = 0; i < td_list.length; i++) {
                            if (td_list[i].innerHTML == '')
                                td_list[i].innerHTML = '&nbsp;'
                        }
                    }
                    function set_signature_value() {
                        try {
                            var s = document.body.innerHTML
                            return s.substr(s.length-45, 40);
                        } catch(e) {
                        }
                    }
                    function show_code_snippet(evt, snippet_id, line_number, filename) {
                        hide_popups();
                        evt = (window.event) ? window.event : evt;
                        var s = document.getElementById('code_snippet');
                        if (!s) return;
                        var snippet = document.getElementById('c_' + snippet_id).innerHTML;
                        var e = snippet.split("~~~");
                        var t = '';
                        for (var i=0; i<e.length-1; i++) {
                            t += "<span class=\"line_number\">" + (line_number-3+i) + "</span>";
                            if (i+1 == (Math.floor((1+e.length)/2))) {
                                t += "<span class=\"highlight\">" + e[i] + "</span><br/>";
                            } else {
                                t += e[i] + "<br/>";
                            }
                        }
                        // Code Snippet
                        s.innerHTML = t;
                        var ps = getPageSize();
                        s.style.left = mouseX(evt);
                        if (parseInt(s.style.left) + 575 > ps[2]) {
                            s.style.left = (parseInt(ps[2]) - 595) + "px";
                        }
                        s.style.top = mouseY(evt);
                        s.style.display = 'block';
                        s.style.height = getCSSValue('code_snippet', 'height');
                        s.style.width = getCSSValue('code_snippet', 'width');
                        evt.cancelBubble = true;
                        evt.returnValue = false;                        
                    }

                    function toggle_snippet() {
                        var s = document.getElementById('code_snippet');
                        if (!s) return;
                        if (s.style.left == '15%') {
                        hide_popups();
                        return;
                        }
                        s.style.left = '15%';
                        s.style.width = '70%';
                        s.style.top = '10%';
                        s.style.height = '50%';
                    }

                    function show_fixed_snippet(evt, snippet_id, line_number, filename, fixed_line) {
                        hide_popups();
                        evt = (window.event) ? window.event : evt;
                        var s = document.getElementById('code_snippet');
                        if (!s) return;
                        var snippet = document.getElementById('c_' + snippet_id).innerHTML;
                        var e = snippet.split("~~~");
                        var t = '';
                            t += '<div class="toggle_snippet_size" title="Toggle Snippet Size" onclick="toggle_snippet();">&#x25A0;</div>';
                        for (var i=0; i<e.length-1; i++) {
                            t += "<span class=\"line_number\">" + (line_number-3+i) + "</span>";
                            if (i+1 == (Math.floor((1+e.length)/2))) {
                                t += "<span class=\"highlight nowrap\">" + fixed_line + "</span><br/>";
                            } else {
                                t += e[i] + "<br/>";
                            }
                        }                   
                        // Code Snippet
                        s.innerHTML = t;
                        var ps = getPageSize();
                        s.style.left = mouseX(evt);
                        if (parseInt(s.style.left) + 575 > ps[2]) {
                            s.style.left = (parseInt(ps[2]) - 595) + "px";
                        }
                        s.style.top = mouseY(evt);
                        s.style.height = getCSSValue('code_snippet', 'height');
                        s.style.width = getCSSValue('code_snippet', 'width');
                        s.style.display = 'block';
                        evt.cancelBubble = true;
                        evt.returnValue = false;                        
                    }


                    
                    function show_description(evt, plugin_id) {
                        hide_popups();
                        evt = (window.event) ? window.event : evt;
                        var s = document.getElementById('d_' + plugin_id);
                        if (!s) return;

                        s.style.top = mouseY(evt);
                        s.style.left = mouseX(evt);
                        var ps = getPageSize();
                        if (parseInt(s.style.left) + 595 > ps[2]) {
                            s.style.left = (parseInt(ps[2]) - 575) + "px";
                        }
                        
                        s.style.display = 'block';
                        evt.cancelBubble = true;
                        evt.returnValue = false;
                        
                        open_description = plugin_id;
                    }
                    
                    function hide_popups() {
                        document.getElementById('code_snippet').style.display = 'none';
                        document.getElementById('file_base').style.display = 'none';
                        document.getElementById('ignore_list').style.display = 'none';
                        // document.getElementById('ignore_save_as').style.display = 'none';
                        if (document.getElementById('d_' + open_description)) {
                            try {
                            document.getElementById('d_' + open_description).style.display = 'none';
                            } catch(e) {
                            // ignore
                            }
                        }
                    }

                    function mouseX(evt) {if (evt.pageX) return evt.pageX; else if (evt.clientX)return evt.clientX + (document.documentElement.scrollLeft ?  document.documentElement.scrollLeft : document.body.scrollLeft); else return null;}

                    function mouseY(evt) {if (evt.pageY) return evt.pageY; else if (evt.clientY)return evt.clientY + (document.documentElement.scrollTop ? document.documentElement.scrollTop : document.body.scrollTop); else return null;}

                    function getPageSize(){
                        var xScroll, yScroll;
                        if (window.innerHeight && window.scrollMaxY) {  
                            xScroll = document.body.scrollWidth;
                            yScroll = window.innerHeight + window.scrollMaxY;
                        } else if (document.body.scrollHeight > document.body.offsetHeight){ // all but Explorer Mac
                            xScroll = document.body.scrollWidth;
                            yScroll = document.body.scrollHeight;
                        } else { // Explorer Mac...would also work in Explorer 6 Strict, Mozilla and Safari
                            xScroll = document.body.offsetWidth;
                            yScroll = document.body.offsetHeight;
                        }
                        
                        var windowWidth, windowHeight;
                        if (self.innerHeight) { // all except Explorer
                            windowWidth = self.innerWidth;
                            windowHeight = self.innerHeight;
                        } else if (document.documentElement && document.documentElement.clientHeight) { // Explorer 6 Strict Mode
                            windowWidth = document.documentElement.clientWidth;
                            windowHeight = document.documentElement.clientHeight;
                        } else if (document.body) { // other Explorers
                            windowWidth = document.body.clientWidth;
                            windowHeight = document.body.clientHeight;
                        }   
                        
                        // for small pages with total height less then height of the viewport
                        if(yScroll < windowHeight){
                            pageHeight = windowHeight;
                        } else { 
                            pageHeight = yScroll;
                        }
                    
                        // for small pages with total width less then width of the viewport
                        if(xScroll < windowWidth){  
                            pageWidth = windowWidth;
                        } else {
                            pageWidth = xScroll;
                        }
                    
                    
                        arrayPageSize = new Array(pageWidth,pageHeight,windowWidth,windowHeight) 
                        return arrayPageSize;
                    }
                    
                    function cancel_event(evt) {
                        evt = (window.event) ? window.event : evt;
                        if (!evt) return true;
                        if (evt.preventDefault) evt.preventDefault();
                        evt.cancelBubble = true;
                        evt.returnValue = false;
                        return true;
                    }       
                    
                    function attachColumnSorting() {    
                        var tags = document.getElementsByTagName("table");
                        for (var i=0; i<tags.length; i++) {
                            var cn = tags[i].attributes;
                            for (var j=0; j<cn.length; j++) {
                                if (cn[j].name == 'sortable' && cn[j].value == 'true') {
                                    try {
                                        tags[i].addEventListener('click', sortColumn, false);
                                    } catch(e) {
                                        tags[i].attachEvent('onclick', sortColumn);
                                    }
                
                                    // Use pointer cursor for the top row
                                    try {
                                        var obj = tags[i].tHead ? tags[i].tHead : tags[i].rows[0];
                                        obj.style.cursor = ie5 ? 'hand' : 'pointer';
                                    } catch(e) {
                                    throw e;
                                    }
                                }
                            }
                        }
                    }
                        
                    function attach_event(evt, event_name, callback) {
                        try { 
                            evt.addEventListener(event_name, callback, false);
                        } catch(e) {
                            try {
                                evt.attachEvent("on" + event_name, callback);
                            } catch(e2) {
                            }
                        }
                    }
                                    
                    var dom = (document.getElementsByTagName) ? true : false;
                    var ie5 = (document.getElementsByTagName && document.all) ? true : false;
                    var arrowUp, arrowDown;
                    
                    if (ie5 || dom)
                        initSortTable();
                    
                    function initSortTable() {
                        arrowUp = document.createElement("SPAN");
                        arrowUp.style.display = 'none';
                        if (ie5) {
                            var tn = document.createTextNode("5");
                            arrowUp.appendChild(tn);
                            arrowUp.className = "arrow";
                    
                            arrowDown = document.createElement("SPAN");
                            arrowDown.style.display = 'none';
                            var tn = document.createTextNode("6");
                            arrowDown.appendChild(tn);
                            arrowDown.className = "arrow";
                        } else {
                            var tn = document.createElement("img");
                            tn.src = 'images/arrow.up.gif';
                            arrowUp.appendChild(tn);
                            arrowUp.className = "arrow";
                    
                            arrowDown = document.createElement("SPAN");
                            arrowDown.style.display = 'none';
                            var tn2 = document.createElement("img");
                            tn2.src = 'images/arrow.down.gif';
                            arrowDown.appendChild(tn2);
                            arrowDown.className = "arrow";
                        }
                    }

                    function sortTable(tableNode, nCol, bDesc, sType) {
                        var tBody = tableNode.tBodies[0];
                        var trs = tBody.rows;
                        var trl= trs.length;
                        var a = new Array();
                        
                        for (var i = 0; i < trl; i++) {
                            a[i] = trs[i];
                        }
                        
                        var start = new Date;
                        a.sort(compareByColumn(nCol,bDesc,sType));
                        
                        for (var i = 0; i < trl; i++) {
                            tBody.appendChild(a[i]);
                        }
                        
                        // check for onsort
                        if (typeof tableNode.onsort == "string")
                            tableNode.onsort = new Function("", tableNode.onsort);
                        if (typeof tableNode.onsort == "function")
                            tableNode.onsort();
                    }
                    
                    function CaseInsensitiveString(s) {
                        return String(s).toUpperCase();
                    }
                    
                    function parseDate(s) {
                        return Date.parse(s.replace(/\-/g, '/'));
                    }
                    
                    function toNumber(s) {
                        return Number(s.replace(/[^0-9\.]/g, ""));
                    }
                    
                    function compareByColumn(nCol, bDescending, sType) {
                        var c = nCol;
                        var d = bDescending;
                        
                        var fTypeCast = String;
                        
                        if (sType == "Number")
                            fTypeCast = Number;
                        else if (sType == "Date")
                            fTypeCast = parseDate;
                        else if (sType == "CaseInsensitiveString")
                            fTypeCast = CaseInsensitiveString;
                    
                        return function (n1, n2) {
                            if (fTypeCast(getInnerText(n1.cells[c])) < fTypeCast(getInnerText(n2.cells[c])))
                                return d ? -1 : +1;
                            if (fTypeCast(getInnerText(n1.cells[c])) > fTypeCast(getInnerText(n2.cells[c])))
                                return d ? +1 : -1;
                            return 0;
                        };
                    }
                    
                    function sortColumnWithHold(e) {
                        // find table element
                        var el = ie5 ? e.srcElement : e.target;
                        var table = getParent(el, "TABLE");
                        
                        // backup old cursor and onclick
                        var oldCursor = table.style.cursor;
                        var oldClick = table.onclick;
                        
                        // change cursor and onclick    
                        table.style.cursor = "wait";
                        table.onclick = null;
                        
                        // the event object is destroyed after this thread but we only need
                        // the srcElement and/or the target
                        var fakeEvent = {srcElement : e.srcElement, target : e.target};
                        
                        // call sortColumn in a new thread to allow the ui thread to be updated
                        // with the cursor/onclick
                        window.setTimeout(function () {
                            sortColumn(fakeEvent);
                            // once done resore cursor and onclick
                            table.style.cursor = oldCursor;
                            table.onclick = oldClick;
                        }, 100);
                    }
                    
                    function sortColumn(e) {
                        var tmp = e.target ? e.target : e.srcElement;
                        var tHeadParent = getParent(tmp, "THEAD");
                        var el = getParent(tmp, "TD");
                        if (el == null) el = getParent(tmp, "TH");
                    
                        if (tHeadParent == null)
                            return;
                    
                        if (el != null) {
                            var p = el.parentNode;
                    
                            var i;
                    
                            // typecast to Boolean
                            el._descending = !Boolean(el._descending);
                    
                            if (tHeadParent.arrow != null) {
                                if (tHeadParent.arrow.parentNode != el) {
                                    tHeadParent.arrow.parentNode._descending = null;    //reset sort order      
                                }
                                tHeadParent.arrow.parentNode.removeChild(tHeadParent.arrow);
                            }
                    
                            if (el._descending)
                                tHeadParent.arrow = arrowUp.cloneNode(true);
                            else
                                tHeadParent.arrow = arrowDown.cloneNode(true);
                    
                            el.appendChild(tHeadParent.arrow);
                    
                                
                    
                            // get the index of the td
                            var cells = p.cells;
                            var l = cells ? cells.length : 0;
                            for (i = 0; i < l; i++) {
                                if (cells[i] == el) break;
                            }
                            
                            if (!cells) {
                                var j=0;
                                for (i=0; i<p.childNodes.length; i++) {
                                    if (p.childNodes[i].nodeName.match(/TH|TD/)) {
                                        if (p.childNodes[i] == el) break;
                                        else j++;
                                    }
                                }
                                i=j;
                            }
                            
                            var table = getParent(el, "TABLE");
                            // can't fail
                            
                            sortTable(table,i,el._descending, el.getAttribute("type"));
                        }
                    }
                    
                    function getInnerText(el) {
                        if (ie5) return el.innerText;   //Not needed but it is faster
                        
                        var str = "";
                        
                        var cs = el.childNodes;
                        var l = cs.length;
                        for (var i = 0; i < l; i++) {
                            switch (cs[i].nodeType) {
                                case 1: //ELEMENT_NODE
                                    if (cs[i].tagName == "INPUT" &&
                                        cs[i].getAttribute("type").toLowerCase() == "text") {
                                        str += cs[i].value;
                                    } else {
                                        str += getInnerText(cs[i]);
                                    }
                                    break;
                                case 3: //TEXT_NODE
                                    str += cs[i].nodeValue;
                                    break;
                            }
                        }   
                        return str;
                    }
                    
                    function getParent(el, pTagName) {
                        if (el == null) return null;
                        else if (el.nodeType == 1 && el.tagName.toLowerCase() == pTagName.toLowerCase())
                            return el;
                        else
                            return getParent(el.parentNode, pTagName);
                    }
                    function toggle_scanned_files() {
                        var elt = document.getElementById('target_list');
                        if (!elt) return;
                        if (elt.style.display != 'block') {
                            elt.style.display = 'block';
                        } else {
                            elt.style.display = 'none';
                        }
                    }

                    function getCSSValue(selector, property) {
                        var i, r, s = document.styleSheets && document.styleSheets[0];
                        if(s) {
                        r = s.rules ? s.rules : s.cssRules; 
                                if(r) {
                            i = r.length; 
                            while (i--) {
                            if(r[i] && r[i].selectorText && r[i].selectorText.toLowerCase() === selector.toLowerCase()) {
                                    return ( r[i].style[property] );
                            }
                            }
                        }
                        }
                        return null;
                    }
                    
                    function change_filename_base() {
                        var new_base_dir = document.getElementById('new_base_dir').value;
                        new_base_dir = new_base_dir.replace(/\\\\/g, "/");
                        new_base_dir = new_base_dir.replace(/\\/+/g, "/");
                        new_base_dir = new_base_dir.replace(/\\/$/g, "");
                        
                        var orig_base_dir = document.getElementById('base_dir').value.toLowerCase();
                        orig_base_dir = orig_base_dir.replace(/\\\\/g, "/");
                        orig_base_dir = orig_base_dir.replace(/\\/+/g, "/");
                        orig_base_dir = orig_base_dir.replace(/\\/$/g, "");
                        
                        var tags = document.getElementsByTagName("a");
                        for (var i=0; i<tags.length; i++) {
                            if ( tags[i].getAttribute("source_code_link") == "true" != null ) {
                                var href = tags[i].href.toLowerCase();
                                href = href.replace(orig_base_dir, new_base_dir);
                                tags[i].href = href;
                            }
                        }
                        document.getElementById('base_dir').value = new_base_dir;
                        document.getElementById('new_base_dir').value = new_base_dir;
                        hide_popups();
                    }
                    function show_change_base(evt) {
                        hide_popups();
                        evt = (window.event) ? window.event : evt;
                        var s = document.getElementById('file_base');
                        if (!s) return;

                        s.style.top = mouseY(evt);
                        s.style.left = mouseX(evt);
                        var ps = getPageSize();
                        if (parseInt(s.style.left) + 215 > ps[2]) {
                            s.style.left = (parseInt(ps[2]) - 235) + "px";
                        }
                        
                        s.style.display = 'block';
                        evt.cancelBubble = true;
                        evt.returnValue = false;
                    }
                    function show_attachment(idx) {
                        var i = 1;      // skip the first one, the 'hide' index
                        while(true) {
                            var obj = document.getElementById("attachment_" + i);
                            if (obj) 
                                obj.style.display = (i++ == idx ? 'block' : 'none');
                            else
                                return;
                        }
                    }

                    function ignore(row_id, filename, line_number, category) {
                        var row = document.getElementById('row_' + row_id);
                        var row_value = "        <ignore filename=\"" + filename + "\" line_number=\"" + line_number + "\" category=\"" + category + "\"/>" + CRLF;
                        var row_header = document.getElementById('row_' + row_id).cells.item(0);

                        if (row.getAttribute("ignore") == "true") {
                        row.setAttribute("ignore", "false");
                        row.setAttribute("ignore_text", "");
                        row_header.style.textDecoration = '';
                        } else {
                        row.setAttribute("ignore", "true");
                        row.setAttribute("ignore_text", row_value);
                        row_header.style.textDecoration = 'line-through';
                        }
                    }

                    function save_ignore_list(evt) {
                        evt = (window.event) ? window.event : evt;
                        var elts = document.getElementsByTagName("tr");
                        var result = '<?xml version="1.0" encoding="UTF-8" ?>' + CRLF + CRLF + '<!-- Save this file and include it in the Yasca command line -->' + CRLF + CRLF + "<yasca>" + CRLF + "    <ignore_list>" + CRLF;

                        for (var i=0; i<elts.length; i++) {
                        if (elts[i].getAttribute("ignore") == "true") {
                            result += elts[i].getAttribute("ignore_text");
                        }
                        }
                        result += "    </ignore_list>" + CRLF + "</yasca>" + CRLF;

                        var ignore_list = document.getElementById('ignore_list');
                        ignore_list.value = result;
                        ignore_list.style.display = 'block';
                        if (document.all) {
                            //document.getElementById('ignore_save_as').style.display = 'block';
                        }
                        evt.cancelBubble = true;
                        evt.returnValue = false;                        

                    }
                    function save_as(evt) {
                        evt = (window.event) ? window.event : evt;
                        if (document.all) {
                        try {
                            fso = new ActiveXObject("Scripting.FileSystemObject");
                            fso.CreateTextFile("./yasca-ignore", true);
                            a = fso.GetFile("./yasca-ignore");
                            b = a.OpenAsTextStream(2, 0);
                            b.Write(document.getElementById('ignore_list').value);
                            b.Close();
                        } catch(e) {
                            alert("An error occurred saving ./yasca-ignore. Please save the file manually.");
                        }
                        }
                        cancel_event(evt);
                    }

                    // Toggles an entire category (show/hide)
					function toggle_category(evt) {
						try {
							evt = (window.event) ? window.event : evt;
							var elt = (evt.srcElement) ? evt.srcElement : evt.target;

							var hide = true;
							
							if (elt.innerHTML == "hide") {
								elt.innerHTML = "show";
							} else {
								elt.innerHTML = "hide";
								hide = false;
							}

							while (elt != null && elt.nodeName != "TR") elt = elt.parentNode;
							while(elt != null) {
								elt = elt.nextElementSibling || elt.nextSibling;
								if (elt.nodeName == "TR" && elt.id != "") {
									elt.style.display = hide ? 'none' : '';
									continue;
								} else {
									break;
								}
							}
						} catch(e) {
							// error
						}
					}

                </script>
                
            </head>
    
            <textarea id="ignore_list" onclick="cancel_event(event);"></textarea>
<!--
            <div id="ignore_save_as" style="display:none; right: 10%;position:absolute;top:10%;padding:8px;padding-right:20px;font-size:12px;"><a href="javascript:void(0);" onclick="save_as(event);">Save As</a></div>
-->
            <input type="hidden" name="base_dir" id="base_dir" value="$base_dir"/>
            <body onload="on_body_load();" onclick="hide_popups();">
              <div id="code_snippet" onclick="cancel_event(event);"></div>
              <div id="file_base" onclick="cancel_event(event);">
                Enter new base location for the files:<br/>
                <center>
                    <input type="text" id="new_base_dir" value="$base_dir" />
                </center>
                <div style="text-align: right; padding-right: 15px;">
                    <a href="javascript:void(0);" onclick="change_filename_base();">change</a>
                    &nbsp;&nbsp;&nbsp;
                    <a href="javascript:void(0);" onclick="hide_popups();">cancel</a>
                </div>
              </div>
              <table class="header_table" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="header_title" nowrap>Yasca</td>
                    <td style="width: 100%;">
                    <div id="attachment_list" style="float:right;$attachment_list_style">Attachments: $attachment_list_select_box</div>
                    <div>
                    <table style="border:0;">
                        <tr><td class="header_left" nowrap>Yasca Version:</td><td class="header_right">$version [ <a target="_blank" href="http://sourceforge.net/projects/yasca/files/">check for updates</a> ]</td></tr>
                        <tr><td class="header_left" nowrap>Report Generated:</td><td class="header_right">$generation_date</td></tr>
                        <tr><td class="header_left" nowrap>Options:</td><td class="header_right">
                        [ <a href="javascript:void(0);" onclick="show_change_base(event);">change links</a> | <a href="javascript:void(0);" onclick="save_ignore_list(event);">save ignore list</a> | <a href="http://yasca.sourceforge.net/userguide.php" target="_blank">user guide</a> | <a href="http://yasca.sourceforge.net/feedback.php">send feedback</a> ]</td></tr>
                    </table>
                    </div>
                    </td>
                </tr>
              </table>
              $attachment_list
              <div id="results">
                <table cellpadding="0" border="0" cellspacing="2" sortable="true">
                    <thead>
                        <th nowrap>#</th>
                        <th nowrap>Location</th>
                        <th nowrap>Message / Source Line</th>
                    </thead>
END;
        }
        
        protected function get_postamble() {
            $ADVERTISEMENT = Yasca::getAdvertisementText("HTML");

            return <<<END
                </table>
                <div style="text-align:center;font-size:smaller;color:black;font-weight:bold;background-color:#99FFFF">{$ADVERTISEMENT}</div>
              </div>
            </body>
        </html>
END;
        }   

    protected function has_attachments() {
        $yasca =& Yasca::getInstance();
        if (count($yasca->attachment_list) > 0) {
            return true;
        }
        return false;
    }
    /** 
     * Generates a list of DIVs that contain the attachment contents.
     */
    protected function generate_attachment_list() {
        $yasca =& Yasca::getInstance();
        $yasca->log_message("There were " . count($yasca->attachment_list) . " attachments found.", E_USER_NOTICE);
        $html = "";
        $index = 1;     // skip the first one, it's the 'hide' index
        foreach ($yasca->attachment_list as $attachment_ref) {
            $attachment = explode(",", $attachment_ref, 2);
            $attachment_description = (count($attachment) > 1 ? $attachment[1] : $attachment_ref);
            
            $attachment = $yasca->general_cache[$attachment_ref];
            if (is_array($attachment) && count($attachment) == 0) {
                $yasca->log_message("An attachment was defined ($attachment_ref), but no contents were found in the general cache.", E_USER_WARNING);
                continue;
            }
            
            $html .= "<div id=\"attachment_$index\" class=\"attachment_box\" style=\"display:none;\">\n";
            $html .= "<h4 style=\"margin-top:0; margin-bottom:8px;\">$attachment_description</h4>\n";
            if (is_array($attachment)) {
                $html .= implode("<br/>", $attachment);
            } else {
                $html .= $attachment;
            }
            $html .= "</div>\n";
            ++$index;
        }
        return $html;
    }
    
    /**
     * Generates a dropdown select box to choose a particular attachment to show.
     */
    protected function generate_attachment_select_box() {
        $yasca =& Yasca::getInstance();
        $html = '<select id="attachment_select_box" onchange="show_attachment(this.selectedIndex);">';
        $html .= '<option></option>';
        foreach ($yasca->attachment_list as $attachment_ref) {
            $attachment = explode(",", $attachment_ref, 2);
            $attachment_description = (count($attachment) > 1 ? $attachment[1] : $attachment_ref);

            $attachment = $yasca->general_cache[$attachment_ref];
            if (is_array($attachment) && count($attachment) == 0) { // need to do this to keep the indices in sync
                continue;
            }
            $html .= "<option>$attachment_description</option>";
        }
        $html .= '</select>';
        return $html;
    }
}
?>
