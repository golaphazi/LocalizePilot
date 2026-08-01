(function(){
    'use strict';
    function selectedProvider(){var selected=document.querySelector('input[name="next_translate_settings[translation_provider]"]:checked');return selected?selected.value:'translatex';}
    function updateProvider(){var provider=selectedProvider();document.querySelectorAll('.nt-provider-panel').forEach(function(panel){panel.hidden=panel.getAttribute('data-provider')!==provider;});document.querySelectorAll('.nt-provider-card').forEach(function(card){var input=card.querySelector('input');card.classList.toggle('is-selected',!!input&&input.checked);});}
    document.addEventListener('change',function(event){if(event.target.matches('input[name="next_translate_settings[translation_provider]"]')){updateProvider();}});
    document.querySelectorAll('.nt-reveal-key').forEach(function(button){button.addEventListener('click',function(){var input=button.parentNode.querySelector('input');var show=input.type==='password';input.type=show?'text':'password';button.textContent=show?localizePilotAdmin.hide:localizePilotAdmin.show;});});
    var testButton=document.getElementById('next-translate-test-api');
    if(testButton){testButton.addEventListener('click',function(){
        var result=document.getElementById('next-translate-test-result');
        var language=document.getElementById('next-translate-test-language');
        var provider=selectedProvider();
        var panel=document.querySelector('.nt-provider-panel[data-provider="'+provider+'"]');
        var keyInput=panel?panel.querySelector('input[type="password"],input[type="text"]'):null;
        var modelInput=panel?panel.querySelector('.nt-provider-model'):null;
        var style=document.getElementById('localizepilot-ai-style');
        var instructions=document.getElementById('localizepilot-ai-instructions');
        var temperature=document.getElementById('localizepilot-ai-temperature');
        var maxTokens=document.getElementById('localizepilot-ai-max-tokens');
        testButton.disabled=true;testButton.textContent=localizePilotAdmin.testing;result.textContent='';result.className='';
        var form=new FormData();
        form.append('action','next_translate_test_api');
        form.append('nonce',localizePilotAdmin.nonce);
        form.append('language',language?language.value:'es');
        form.append('provider',provider);
        form.append('api_key',keyInput?keyInput.value:'');
        form.append('model',modelInput?modelInput.value:'');
        form.append('style',style?style.value:'natural');
        form.append('instructions',instructions?instructions.value:'');
        form.append('temperature',temperature?temperature.value:'0.2');
        form.append('max_tokens',maxTokens?maxTokens.value:'8192');
        fetch(localizePilotAdmin.ajaxUrl,{method:'POST',credentials:'same-origin',body:form}).then(function(response){return response.json();}).then(function(data){result.textContent=data.data&&data.data.message?data.data.message:'Unknown response';result.className=data.success?'is-success':'is-error';}).catch(function(error){result.textContent=error.message;result.className='is-error';}).finally(function(){testButton.disabled=false;testButton.textContent=localizePilotAdmin.test;});
    });}
    document.querySelectorAll('.nt-copy-code').forEach(function(button){
        button.addEventListener('click',function(){
            var target=document.getElementById(button.getAttribute('data-copy-target'));
            if(!target){return;}
            var text=target.textContent||'';
            var complete=function(){
                var original=button.textContent;
                button.textContent=(window.localizePilotAdmin&&localizePilotAdmin.copied)?localizePilotAdmin.copied:'Copied';
                button.classList.add('is-copied');
                window.setTimeout(function(){button.textContent=original;button.classList.remove('is-copied');},1600);
            };
            if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(complete);return;}
            var textarea=document.createElement('textarea');textarea.value=text;textarea.setAttribute('readonly','');textarea.style.position='fixed';textarea.style.opacity='0';document.body.appendChild(textarea);textarea.select();try{document.execCommand('copy');complete();}catch(error){}document.body.removeChild(textarea);
        });
    });
    updateProvider();
})();
