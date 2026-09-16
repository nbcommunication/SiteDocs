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
			window.print();
			printButton.addEventListener('click', () => window.print());
		}
	});
}
