/*****************************************/
// Name: Javascript Textarea HTML Editor
// Version: 1.3
// Author: Balakrishnan
// Last Modified Date: 25/Jan/2009
// License: Free
// URL: http://www.corpocrat.com
/******************************************/

var textarea;
var content;
document.write("<link href=\"js-editor/styles.css\" rel=\"stylesheet\" type=\"text/css\">");

function edToolbar(obj) {
   
    document.write("<div class=\"mce-panel mce-jseditor\">");
	document.write("<i class=\"fa fa-bold button\" name=\"btnBold\" title=\"Bold\" onClick=\"doAddTags('<strong>','</strong>','" + obj + "')\"></i>");
    document.write("<i class=\"fa fa-italic button\" name=\"btnItalic\" title=\"Italic\" onClick=\"doAddTags('<em>','</em>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-underline button\" name=\"btnUnderline\" title=\"Underline\" onClick=\"doAddTags('<u>','</u>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-strikethrough button\" name=\"btnstrikethrough\" title=\"Strikethrough\" onClick=\"doAddTags('<del>','</del>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-arrows-h button\" name=\"btnarrows\" title=\"Horizontal rule\" onClick=\"doAddTag('<hr>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-comment button\" name=\"btcomment\" title=\"Comment block\" onClick=\"doAddTags('<!--  ','-->','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-paragraph button\" name=\"btnparagraph\" title=\"Paragraph\" onClick=\"doAddTags('<p>','</p>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-link button\" name=\"btnLink\" title=\"Insert Link\" onClick=\"doURL('" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-list-ol button\" name=\"btnList-ol\" title=\"Ordered List\" onClick=\"doList('<ol>','</ol>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-list button\" name=\"btnList\" title=\"Unordered List\" onClick=\"doList('<ul>','</ul>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-align-left button\" name=\"btnalign-left\" title=\"Align left\" onClick=\"doAddTags('<p style=&quot;text-align:left&quot;>','</p>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-align-center button\" name=\"btnalign-center\" title=\"Align center\" onClick=\"doAddTags('<p style=&quot;text-align:center&quot;>','</p>','" + obj + "')\"></i>");
	document.write("<i class=\"fa fa-align-right button\" name=\"btnalign-right\" title=\"Align right\" onClick=\"doAddTags('<p style=&quot;text-align:right&quot;>','</p>','" + obj + "')\"></i>");
    document.write("</div>");
}

function doImage(obj)
{
textarea = document.getElementById(obj);
var url = prompt('Enter the Image URL:','http://');

var scrollTop = textarea.scrollTop;
var scrollLeft = textarea.scrollLeft;

if (url != '' && url != null) {

	if (document.selection) 
			{
				textarea.focus();
				var sel = document.selection.createRange();
				sel.text = '<img src="' + url + '">';
			}
   else 
    {
		var len = textarea.value.length;
	    var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		
        var sel = textarea.value.substring(start, end);
	    //alert(sel);
		var rep = '<img src="' + url + '">';
        textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
		textarea.scrollTop = scrollTop;
		textarea.scrollLeft = scrollLeft;
	}
 }
}

function doURL(obj)
{
var sel;
textarea = document.getElementById(obj);
var url = prompt('Enter the URL:','http://');
var scrollTop = textarea.scrollTop;
var scrollLeft = textarea.scrollLeft;

if (url != '' && url != null) {

	if (document.selection) 
			{
				textarea.focus();
				var sel = document.selection.createRange();
				
				if(sel.text==""){
					sel.text = '<a href="' + url + '">' + url + '</a>';
					} else {
					sel.text = '<a href="' + url + '">' + sel.text + '</a>';
					}
				//alert(sel.text);
				
			}
   else 
    {
		var len = textarea.value.length;
	    var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		
		var sel = textarea.value.substring(start, end);
		
		if(sel==""){
		sel=url; 
		} else
		{
        var sel = textarea.value.substring(start, end);
		}
	    //alert(sel);
		
		
		var rep = '<a href="' + url + '">' + sel + '</a>';;
        textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
		textarea.scrollTop = scrollTop;
		textarea.scrollLeft = scrollLeft;
	}
 }
}

function doAddTag(tag1,obj)
{
textarea = document.getElementById(obj);
	// Code for IE
		if (document.selection)
			{
				textarea.focus();
				var sel = document.selection.createRange();
				//alert(sel.text);
				sel.text = tag1;
			}
   else
    {  // Code for Mozilla Firefox
		var len = textarea.value.length;
	    var start = textarea.selectionStart;
		var end = textarea.selectionEnd;

		var scrollTop = textarea.scrollTop;
		var scrollLeft = textarea.scrollLeft;

        var sel = textarea.value.substring(start, end);
	    //alert(sel);
		var rep = tag1;
        textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);

		textarea.scrollTop = scrollTop;
		textarea.scrollLeft = scrollLeft;
	}
}

function doAddTags(tag1,tag2,obj)
{
textarea = document.getElementById(obj);
	// Code for IE
		if (document.selection)
			{
				textarea.focus();
				var sel = document.selection.createRange();
				//alert(sel.text);
				sel.text = tag1 + sel.text + tag2;
			}
   else
    {  // Code for Mozilla Firefox
		var len = textarea.value.length;
	    var start = textarea.selectionStart;
		var end = textarea.selectionEnd;

		var scrollTop = textarea.scrollTop;
		var scrollLeft = textarea.scrollLeft;

        var sel = textarea.value.substring(start, end);
	    //alert(sel);
		var rep = tag1 + sel + tag2;
        textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);

		textarea.scrollTop = scrollTop;
		textarea.scrollLeft = scrollLeft;
	}
}

function doList(tag1,tag2,obj){
textarea = document.getElementById(obj);

// Code for IE
		if (document.selection) 
			{
				textarea.focus();
				var sel = document.selection.createRange();
				var list = sel.text.split('\n');
		
				for(i=0;i<list.length;i++) 
				{
				list[i] = '<li>' + list[i] + '</li>';
				}
				//alert(list.join("\n"));
				sel.text = tag1 + '\n' + list.join("\n") + '\n' + tag2;
				
			} else
			// Code for Firefox
			{

		var len = textarea.value.length;
	    var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		var i;
		
		var scrollTop = textarea.scrollTop;
		var scrollLeft = textarea.scrollLeft;

		
        var sel = textarea.value.substring(start, end);
	    //alert(sel);
		
		var list = sel.split('\n');
		
		for(i=0;i<list.length;i++) 
		{
		list[i] = '<li>' + list[i] + '</li>';
		}
		//alert(list.join("<br>"));
        
		
		var rep = tag1 + '\n' + list.join("\n") + '\n' +tag2;
		textarea.value =  textarea.value.substring(0,start) + rep + textarea.value.substring(end,len);
		
		textarea.scrollTop = scrollTop;
		textarea.scrollLeft = scrollLeft;
 }
}