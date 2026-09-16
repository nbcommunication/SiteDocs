/**
 * ProcessSiteDocs.js
 *
 * Auto-loaded by ProcessWire alongside ProcessSiteDocs.module.php.
 *
 */

{
	document.addEventListener('DOMContentLoaded', () => {
		const printButton = document.querySelector('.sitedocs-print-button');
		if(printButton) {
			const titleParts = document.title.split(' •');
			if(titleParts.length > 1) {
				document.title = titleParts.slice(0, -1).join(' •');
			}
			window.print();
			printButton.addEventListener('click', () => window.print());
		}
	});
}
