const eaEmbeddedListHandler = function (event) {
    async function refreshEmbeddedList(list, url) {
        const response = await fetch(url);
        list.innerHTML = await response.text();
        document.dispatchEvent(new Event('ea.embedded-list.refreshed'));
    }

    document.querySelectorAll('.field-embedded-list').forEach((list) => {
        list.querySelectorAll('a:not([data-fetch="true"])').forEach(link => {
            if (link.closest('.actions')) {
                return;
            }
            link.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();
                refreshEmbeddedList(list, link.href);
            });
            link.dataset.fetch = 'true';
        });
    });
}

window.addEventListener('DOMContentLoaded', eaEmbeddedListHandler);
document.addEventListener('ea.embedded-list.refreshed', eaEmbeddedListHandler);
