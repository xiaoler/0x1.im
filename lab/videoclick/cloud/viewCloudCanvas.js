/*********************************************
 * @title : canvas绘制后台教程
 * @date  : 2013-01-29
 * @author: Scholer
 *********************************************/

function checkCanSupport(){
    try {
        document.createElement("canvas").getContext("2d");
        return true;
    }catch(e){
        document.getElementById('bannerDiv').innerHTML="您的浏览器不支持HTML5 Canvas!";
        return false;
    }
}

//绘图主函数
function Main(){
    //cancas对象
    this.context = null;
    //可见图形区域距边框的偏移
    this.offset = 20.5;
    //canvas标签的高度和宽度
    this.H = 0,this.W = 0;
    //设定定时器和速度
    this.interval = null,this.speed = 5;
    //用于标记是否执行完当前定时器函数
    this.trigger = null;
    //当前正在执行步骤和记录总步骤数
    this.level = 0;

}

//向prentNode追加Canvas的标签并初始化画布
Main.prototype.init = function(C,P) {
    this.W = P.offsetWidth;
    this.H = P.offsetHeight;
    P.appendChild(C);
    C.setAttribute('width',this.W);
    C.setAttribute('height',this.H);

    this.context = C.getContext('2d');
    this.context.clearRect(0,0,this.W,this.H);
}

//用于绘制区域观察矩形
/*Main.prototype.rect = function(x,y,w,h,color,lineW){
    this.context.rect(x,y,w,h);
    this.context.lineWidth = lineW;
    this.context.strokeStyle = color;
    this.context.stroke();
}*/

//positive->true or false;逐渐显示 or 逐渐隐藏
Main.prototype.shade = function(img,x,y,w,h,positive){

    var _ = this,i = 0,a = 101;

    var alapha = function(){
        _.context.globalAlpha = 0.01 * (i++);
    }

    if (!positive) {
        i = 100;
        a = -1;
        alapha = function(){
            _.context.globalAlpha = 0.01 * (i--);
        }
    }

    _.interval = window.setInterval(function(){
        _.context.clearRect(x,y,w,h);
        alapha();
        _.context.drawImage(img,x,y);
        
        if (i == a) {
            _.context.globalAlpha = 1;
            _.clear();
        };
    },_.speed);
}

//焦点移动,s=>start,e=>end,r=>基准线;D表示方向
Main.prototype.pointMoving = function(s,e,r,D){
    var pointImg = new Image();
    pointImg.src = './view-cloud-img/point-' + D + '.png';
    var _ = this;

    var dirDraw = {
        up : function(){
            _.context.clearRect(r,e,25,40);
            _.context.drawImage(pointImg,r,e--);
        },
        left : function() {
            _.context.clearRect(e,r,40,25);
            _.context.drawImage(pointImg,e--,r);
        },
        right : function() {
            _.context.clearRect(s,r,30,25);
            _.context.drawImage(pointImg,s++,r);
        },
        down : function(){ 
            _.context.clearRect(r,s,25,30);
            _.context.drawImage(pointImg,r,s++);
        }
    };
    
    _.interval = window.setInterval(function(){

        dirDraw[D]();

        if (s > e) {
            (D == 'up' || D == 'down') ? _.context.clearRect(r,s,40,60)
                                       : _.context.clearRect(s,r,60,40);
            _.clear();
        }
    },_.speed);
}

Main.prototype.flicker = function(img,x,y,w,h,t){

    var _ = this,i = 0;
    var dotDraw = function() {
        _.context.drawImage(img,x,y);
        window.setTimeout(function(){_.context.clearRect(x,y,w,h);},500);
        i++;
    }

    _.interval = window.setInterval(function(){
        dotDraw();
        if (i == t) {
            _.clear();
        }
    },1000);
}

Main.prototype.mouseUpMoving = function(img,s,e,r,w,h){

    var _ = this;

    _.interval = window.setInterval(function(){
        _.context.clearRect(r-1,e,w,h);
        _.context.drawImage(img,r++,e--);
        
        if (s > e) {
            _.clear();
        }
    },_.speed * 2);
}

/*Main.prototype.mouseRightMoving = function(img,s,e,r,w,h){

    var _ = this;

    _.interval = window.setInterval(function(){
        _.context.clearRect(s,r,w,h);
        _.context.drawImage(img,s++,r);
        
        if (s > e) {
            _.clear();
        }
    },_.speed * 2);
}
*/
Main.prototype.clear = function(){
    var _ = this;
    //当前动作结束,清除定时
    window.clearInterval(_.interval);
    //设置level
    if (_.level == (_.trigger.length - 1)){
        _.level = 0;
        setTimeout(function(){
            _.context.clearRect(0,0,_.W,_.H);
            _.trigger[_.level]()
        },3000);
    }
    else {
        _.level++;
        _.trigger[_.level]();
    }
}

window.onload = function(){
    if(!checkCanSupport()){
        return false;
    }

    var canvas = document.createElement("canvas");

    window.main = new Main();
    main.init(canvas,document.getElementById('bannerDiv'));

    var cloudImg = new Image();
    cloudImg.src = './view-cloud-img/vs-02.png';

    var dotImg = new Image();
    dotImg.src = './view-cloud-img/dot.png';

    var rectImg = new Image();
    rectImg.src = './view-cloud-img/vs-03.png';

    var mouseImg = new Image();
    mouseImg.src = './view-cloud-img/vs-05.png';

    var infoImg = new Image();
    infoImg.src = './view-cloud-img/vs-04.png';

    main.trigger = [
       function(){
            main.flicker(dotImg,122,366,10,10,2);
        },
        function(){
            main.pointMoving(105,350,115,'up');
        },
        function(){
            main.pointMoving(122,240,105,'right');
        },
        function(){
            main.flicker(dotImg,264,112,10,10,1);
        },
        function(){
            main.flicker(cloudImg,319,121,162,42,2);
        },
        function(){
            main.flicker(dotImg,525,112,10,10,1);
        },
        function(){
            main.pointMoving(550,650,105,'right');
        },
        function(){
            main.pointMoving(105,340,661,'down');
        },
        function(){
            main.flicker(dotImg,667,368,10,10,1);
        },
        function(){
            main.shade(rectImg,573,402,85,50,true);
        },
        function(){
            main.context.drawImage(mouseImg,570,480);
            main.level++;
            main.trigger[main.level]();
        },
        function(){
            main.mouseUpMoving(mouseImg,430,480,570,13,15);
        },
        function(){
            main.shade(infoImg,655,402,128,65,true);
        }
    ];

    main.trigger[0]();
}