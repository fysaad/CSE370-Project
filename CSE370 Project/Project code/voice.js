/**
 * voice.js
 * Reads instruction text aloud using the browser's built-in
 * text-to-speech (Web Speech API). No server/audio files needed.
 *
 * Call kdSpeak(text, lang) where lang is 'en' or 'bn'.
 */

var kdVoices = [];

function kdLoadVoices() {
    if ('speechSynthesis' in window) {
        kdVoices = window.speechSynthesis.getVoices();
    }
}

if ('speechSynthesis' in window) {
    kdLoadVoices();
    // Chrome loads the voice list asynchronously -- if speak() is
    // called before this fires, it can fail with no sound and no
    // error. This makes sure the list is populated.
    window.speechSynthesis.onvoiceschanged = kdLoadVoices;
}

function kdSpeak(text, lang) {
    if (!text) return;

    if (!('speechSynthesis' in window)) {
        alert(lang === 'bn'
            ? 'দুঃখিত, আপনার ব্রাউজার শব্দ (টেক্সট-টু-স্পিচ) সমর্থন করে না। Chrome বা Edge ব্যবহার করে দেখুন।'
            : 'Sorry, your browser does not support text-to-speech. Try Chrome or Edge.');
        return;
    }

    // Known Chrome bug: speechSynthesis can get stuck "paused" after
    // being idle. Cancel + resume clears that before speaking.
    window.speechSynthesis.cancel();
    window.speechSynthesis.resume();

    var utter = new SpeechSynthesisUtterance(text);
    utter.lang = lang === 'bn' ? 'bn-BD' : 'en-US';
    utter.rate = 0.95;
    utter.pitch = 1;
    utter.volume = 1;

    // Try to pick a voice that actually matches the language, if one
    // exists on this device -- otherwise the browser's default voice
    // is used (which may mispronounce Bangla, but should still be
    // audible).
    if (kdVoices.length) {
        var match = kdVoices.find(function (v) {
            return v.lang && v.lang.toLowerCase().indexOf(utter.lang.toLowerCase().slice(0, 2)) === 0;
        });
        if (match) utter.voice = match;
    }

    utter.onerror = function (e) {
        console.error('KrishiDirect voice error:', e.error, e);
    };
    utter.onstart = function () {
        console.log('KrishiDirect voice: speaking started');
    };

    // IMPORTANT: this must run synchronously inside the click handler.
    // Chrome/Edge require speech synthesis to be triggered directly by
    // a user gesture -- wrapping this in setTimeout() (even a few ms)
    // breaks that link and Chrome silently drops the speech with no
    // error at all.
    window.speechSynthesis.speak(utter);
}
