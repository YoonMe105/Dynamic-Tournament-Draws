/*
|--------------------------------------------------------------------------
| Download the bracket as a PNG image
|--------------------------------------------------------------------------
| Needs html2canvas. Draws the whole bracket (even the part scrolled out of
| view) with a title above it.
*/

function downloadBracket(button, fileName, title) {

    const bracket = document.querySelector('.bracket');

    if (!bracket || typeof html2canvas === 'undefined') {
        alert('The bracket could not be downloaded. Please try again.');
        return;
    }

    const buttonText = button.textContent;

    button.disabled = true;
    button.textContent = 'Preparing...';

    // Copy of the bracket with a title, outside the scroll box, so nothing is cut off
    const sheet = document.createElement('div');

    sheet.className = 'bracket-download-sheet';
    sheet.innerHTML = '<h2 class="bracket-download-title"></h2>';
    sheet.querySelector('h2').textContent = title;
    sheet.appendChild(bracket.cloneNode(true));

    document.body.appendChild(sheet);

    html2canvas(sheet, {
        scale: 2,
        backgroundColor: '#ffffff'
    }).then(function (canvas) {

        const link = document.createElement('a');

        link.download = fileName;
        link.href = canvas.toDataURL('image/png');

        document.body.appendChild(link);
        link.click();
        link.remove();

    }).catch(function () {

        alert('The bracket could not be downloaded. Please try again.');

    }).finally(function () {

        sheet.remove();

        button.disabled = false;
        button.textContent = buttonText;
    });
}
