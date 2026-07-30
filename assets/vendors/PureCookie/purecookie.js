// --- Config --- //
var purecookieDesc =  docy_local_object.privacy_bar_txt; // Description
var purecookieButton = docy_local_object.privacy_bar_btn_txt; // Button text
// ---        --- //

function pureFadeIn(elem, display){
  var el = document.getElementById(elem);
  el.style.opacity = 0;
  el.style.display = display || "flex";

  (function fade() {
    var val = parseFloat(el.style.opacity);
    if (!((val += .02) > 1)) {
      el.style.opacity = val;
      requestAnimationFrame(fade);
    }
  })();
}

function pureFadeOut(elem){
  var el = document.getElementById(elem);
  el.style.opacity = 1;

  (function fade() {
    if ((el.style.opacity -= .02) < 0) {
      el.style.display = "none";
    } else {
      requestAnimationFrame(fade);
    }
  })();
}

function setCookie(name,value,days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "")  + expires + "; path=/";
}

function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

function eraseCookie(name) {
    document.cookie = name+'=; Max-Age=-99999999;';
}

function cookieConsent() {
  if (!getCookie('purecookieDismiss')) {
    document.body.innerHTML += '' +
        '<div class="cookieAcceptBar" id="cookieConsentContainer">' +
            '<div class="cookieDesc"><p>' + purecookieDesc + '</p> </div>' +
            '<div class="cookieButton">' +
                '<button onclick="purecookieDismiss()" class="btn btn-success">' + purecookieButton + '</button>' +
            '</div>' +
        '</div>';
    pureFadeIn("cookieConsentContainer");
  }
}

function purecookieDismiss() {
    setCookie('purecookieDismiss','1',7);
    pureFadeOut("cookieConsentContainer");
}

window.onload = function() { cookieConsent(); };