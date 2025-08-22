document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.adsm-event-views').forEach(function (wrap) {
        const listBtn = wrap.querySelector('.adsm-toggle-btn[data-view="list"]');
        const calBtn  = wrap.querySelector('.adsm-toggle-btn[data-view="calendar"]');
        const list    = wrap.querySelector('.adsm-event-list');
        const cal     = wrap.querySelector('.adsm-event-calendar');
        const hasEmbed = wrap.getAttribute('data-has-embed') === '1';

        if (!listBtn || !calBtn || !list || !cal) return;

        const showList = () => {
            list.classList.remove('d-none');
            cal.classList.add('d-none');
            listBtn.classList.add('active');
            calBtn.classList.remove('active');
        };

        const showCal = () => {
            if (!hasEmbed) return;
            cal.classList.remove('d-none');
            list.classList.add('d-none');
            calBtn.classList.add('active');
            listBtn.classList.remove('active');
        };

        listBtn.addEventListener('click', showList);
        calBtn.addEventListener('click', showCal);
    });
});
