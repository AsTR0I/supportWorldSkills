<?php
/**
 
 */
/*
Plugin Name: timeCounter
Description: timeCounter
Version: 1.0.0
*/


// This just echoes the chosen line, we'll position it later.
function timeCounter() {
	echo "
	<script>
	const wp_block_columns = document.querySelector('.wp-block-columns');
	const p = document.createElement('p');
	let timeStart = sessionStorage.getItem('timeStart');

	if (!timeStart) {
		timeStart = Date.now();
		sessionStorage.setItem('timeStart', timeStart);
	}

	const getTime = () => {
		let timeNow = Date.now();
		const timeSpent = (timeNow - timeStart) / 1000;
		p.textContent = timeSpent + ' секунд';
	}

	getTime();
	setInterval(() => {		
		getTime();
	}, 1000);

	wp_block_columns.appendChild(p);
	</script>";
}

add_action('wp_footer', 'timeCounter');


