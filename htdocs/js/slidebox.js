var isExtended = 0;

function slideSideBar(){

	new Effect.toggle('slideboxContents', 'blind', {scaleX: 'true', scaleY: 'true;', scaleContent: false});

	if(isExtended==0){
		$('slideboxTab').childNodes[0].src = $('slideboxTab').childNodes[0].src.replace(/(\.[^.]+)$/, '-open$1');

		new Effect.Fade('slideboxContents',
   	{ duration:1.0, from:0.0, to:1.0 });

		isExtended++;
	}
	else{
		$('slideboxTab').childNodes[0].src = $('slideboxTab').childNodes[0].src.replace(/-open(\.[^.]+)$/, '$1');

		new Effect.Fade('slideboxContents',
   	{ duration:1.0, from:1.0, to:0.0 });

		isExtended=0;
	}

}

function init(){
	Event.observe('slideboxTab', 'click', slideSideBar, true);
}

Event.observe(window, 'load', init, true);
