'use strict';

const assert = require('node:assert/strict');
const { draftShape, growDraftRows, constants } = require('../assets/js/trb-release-upload.js');

function pair(name, value) {
	return [name, value];
}

function credit(track, index, name, role) {
	return [
		pair(`trb_tracks[${track}][credits][credits][${index}][name]`, name),
		pair(`trb_tracks[${track}][credits][credits][${index}][role]`, role),
	];
}

function testIdZeroRegression() {
	const pairs = [pair('trb_release_type', 'single')];
	for (let track = 0; track < 3; track += 1) {
		pairs.push(pair(`trb_tracks[${track}][title]`, `Brano ${track + 1}`));
		pairs.push(...credit(track, 0, `Partecipante ${track}-A`, 'Producer'));
		pairs.push(...credit(track, 1, `Partecipante ${track}-B`, 'Vocalist'));
		pairs.push(...credit(track, 2, `Partecipante ${track}-C`, 'Piano'));
	}
	pairs.push(...credit(0, 9100, 'Partecipante 0-A', 'Mixing Engineer'));
	pairs.push(...credit(0, 9200, 'Partecipante 0-B', 'Recording Engineer'));

	const shaped = draftShape(pairs);
	assert.equal(shaped.maxTrack, 2, 'three tracks must restore as indexes 0..2');
	assert.equal(shaped.contributors['0-credits'], 2, 'technical role indexes must not become visible rows');
	assert.equal(shaped.contributors['1-credits'], 2);
	assert.equal(shaped.contributors['2-credits'], 2);
	assert.deepEqual(shaped.creditRoles['0-0'], ['Producer', 'Mixing Engineer']);
	assert.deepEqual(shaped.creditRoles['0-1'], ['Vocalist', 'Recording Engineer']);
	assert.equal(shaped.skipped, 0, 'valid technical roles must be preserved, not discarded');
}

function testAdditionalAffectedProfileRegression() {
	const shaped = draftShape([
		pair('trb_release_type', 'single'),
		pair('trb_tracks[0][title]', 'Brano'),
		...credit(0, 0, 'Partecipante', 'Producer'),
		...credit(0, 9100, 'Partecipante', 'Composer'),
		...credit(0, 9101, 'Partecipante', 'Writer'),
		...credit(0, 9102, 'Partecipante', 'Arranger'),
		...credit(0, 9103, 'Partecipante', 'Piano'),
	]);
	assert.equal(shaped.contributors['0-credits'], 0);
	assert.deepEqual(shaped.creditRoles['0-0'], ['Producer', 'Composer', 'Writer', 'Arranger', 'Piano']);
}

function testTwentyFourTrackBoundary() {
	const pairs = [pair('trb_release_type', 'collection')];
	for (let track = 0; track < 24; track += 1) pairs.push(pair(`trb_tracks[${track}][title]`, `Track ${track + 1}`));
	const shaped = draftShape(pairs);
	assert.equal(shaped.maxTrack, 23);
	assert.equal(shaped.skipped, 0);
	let count = 1;
	let clicks = 0;
	const button = { disabled: false, click() { clicks += 1; count += 1; } };
	assert.equal(growDraftRows(button, () => count, shaped.maxTrack + 1, constants.maxTrackIndex + 1), true);
	assert.equal(count, 24);
	assert.equal(clicks, 23);
}

function testNoProgressGuard() {
	let clicks = 0;
	const button = { disabled: false, click() { clicks += 1; } };
	assert.equal(growDraftRows(button, () => 1, 9201, constants.maxVisibleContributorIndex + 1), false);
	assert.equal(clicks, 1, 'a no-op add button must stop after the first failed attempt');
}

function testCorruptIndexesAreBounded() {
	const shaped = draftShape([
		pair('trb_tracks[24][title]', 'Out of range'),
		pair('trb_tracks[0][credits][writers][8999][name]', 'Invalid'),
		pair('trb_tracks[0][title]', 'Valid'),
	]);
	assert.equal(shaped.maxTrack, 0);
	assert.equal(shaped.contributors['0-writers'], undefined);
	assert.equal(shaped.skipped, 2);
}

testIdZeroRegression();
testAdditionalAffectedProfileRegression();
testTwentyFourTrackBoundary();
testNoProgressGuard();
testCorruptIndexesAreBounded();

console.log('release draft restore regressions: ok');
