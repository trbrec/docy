'use strict';

const assert = require('node:assert/strict');
const { draftShape, growDraftRows, draftContributorTargets, isTechnicalDraftPairName, constants } = require('../assets/js/trb-release-upload.js');

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
	assert.equal(shaped.legacyTechnical, 4);
	assert.equal(shaped.unmappedTechnical, 0);
	assert.equal(shaped.pairs.some(([name]) => isTechnicalDraftPairName(name)), false, 'legacy sparse indexes must be removed from the canonical draft');
	assert.ok(shaped.pairs.some(([name, value]) => name === 'trb_tracks[0][credits][credits][0][roles_json]' && value === '["Producer","Mixing Engineer"]'));
	const secondPass = draftShape(shaped.pairs);
	assert.equal(secondPass.legacyTechnical, 0, 'canonical drafts must be idempotent');
	assert.deepEqual(secondPass.creditRoles, shaped.creditRoles);
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
	assert.equal(shaped.legacyTechnical, 8);
	assert.equal(shaped.unmappedTechnical, 0);
	assert.equal(shaped.pairs.some(([name]) => /\[(?:9100|9101|9102|9103)\]/.test(name)), false);
}

function testDenseMultiRoleDraft() {
	const shaped = draftShape([
		pair('trb_release_type', 'single'),
		pair('trb_tracks[0][title]', 'Brano'),
		...credit(0, 0, 'Partecipante', 'Producer'),
		pair('trb_tracks[0][credits][credits][0][roles_json]', '["Producer","Mixing Engineer","Piano"]'),
	]);
	assert.equal(shaped.legacyTechnical, 0);
	assert.equal(shaped.unmappedTechnical, 0);
	assert.deepEqual(shaped.creditRoles['0-0'], ['Producer', 'Mixing Engineer', 'Piano']);
	assert.equal(shaped.pairs.filter(([name]) => name.endsWith('[roles_json]')).length, 1);
}

function testRepeatedNamesKeepRolesOnTheirEncodedRows() {
	const shaped = draftShape([
		pair('trb_tracks[0][title]', 'Brano'),
		...credit(0, 0, 'Stesso nome', 'Producer'),
		...credit(0, 1, 'Stesso nome', 'Vocalist'),
		...credit(0, 9100, 'Stesso nome', 'Mixing Engineer'),
		...credit(0, 9200, 'Stesso nome', 'Piano'),
	]);
	assert.deepEqual(shaped.creditRoles['0-0'], ['Producer', 'Mixing Engineer']);
	assert.deepEqual(shaped.creditRoles['0-1'], ['Vocalist', 'Piano']);
	assert.equal(shaped.unmappedTechnical, 0);

	const ambiguous = draftShape([
		...credit(0, 0, 'Stesso nome', 'Producer'),
		...credit(0, 1, 'Stesso nome', 'Vocalist'),
		...credit(0, 9300, 'Stesso nome', 'Piano'),
	]);
	assert.equal(ambiguous.unmappedTechnical, 1, 'ambiguous roles must stay recoverable instead of being assigned to the wrong person');
	assert.equal(ambiguous.pairs.some(([name]) => name.includes('[9300]')), true);
}

function testTwentyFourTrackBoundary() {
	const pairs = [pair('trb_release_type', 'collection')];
	for (let track = 0; track < 24; track += 1) {
		pairs.push(pair(`trb_tracks[${track}][title]`, `Track ${track + 1}`));
		pairs.push(...credit(track, 0, `Gruppo ${track + 1}`, 'Producer'));
		pairs.push(pair(`trb_tracks[${track}][credits][credits][0][roles_json]`, '["Producer","Vocalist"]'));
	}
	const shaped = draftShape(pairs);
	assert.equal(shaped.maxTrack, 23);
	assert.equal(shaped.skipped, 0);
	assert.equal(shaped.legacyTechnical, 0);
	for (let track = 0; track < 24; track += 1) {
		assert.equal(shaped.contributors[`${track}-credits`], 0);
		assert.deepEqual(shaped.creditRoles[`${track}-0`], ['Producer', 'Vocalist']);
	}
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

function testGlobalRestoreBudget() {
	const plan = draftContributorTargets({
		'0-credits': 249,
		'0-writers': 249,
		'1-credits': 249,
	}, constants.maxRestoredContributors);
	assert.equal(plan.total, 600);
	assert.equal(Object.values(plan.targets).reduce((sum, count) => sum + count, 0), 600);
	assert.equal(plan.partial, true, 'a corrupt draft must be capped before automatic DOM growth');
}

function testCorruptIndexesAreBounded() {
	const shaped = draftShape([
		pair('trb_tracks[24][title]', 'Out of range'),
		pair('trb_tracks[0][credits][writers][8999][name]', 'Invalid'),
		...credit(0, 999999999, 'Orfano', 'Producer'),
		pair('trb_tracks[0][title]', 'Valid'),
	]);
	assert.equal(shaped.maxTrack, 0);
	assert.equal(shaped.contributors['0-writers'], undefined);
	assert.equal(shaped.skipped, 2);
	assert.equal(shaped.legacyTechnical, 2);
	assert.equal(shaped.unmappedTechnical, 1);
	assert.equal(shaped.contributors['0-credits'], undefined, 'technical indexes must never request visible rows');
	assert.equal(shaped.pairs.filter(([name]) => name.includes('[999999999]')).length, 2, 'unmapped legacy data stays recoverable');
}

function testAllPublicationBoundaries() {
	for (const [type, count] of [['single', 3], ['ep', 8], ['album', 18], ['double_album', 24], ['compilation', 24], ['collection', 24]]) {
		const pairs = [pair('trb_release_type', type)];
		for (let track = 0; track < count; track += 1) pairs.push(pair(`trb_tracks[${track}][title]`, `${type} ${track + 1}`));
		const shaped = draftShape(pairs);
		assert.equal(shaped.maxTrack, count - 1, `${type} must restore ${count} tracks`);
		assert.equal(shaped.skipped, 0);
	}
}

testIdZeroRegression();
testAdditionalAffectedProfileRegression();
testDenseMultiRoleDraft();
testRepeatedNamesKeepRolesOnTheirEncodedRows();
testTwentyFourTrackBoundary();
testNoProgressGuard();
testGlobalRestoreBudget();
testCorruptIndexesAreBounded();
testAllPublicationBoundaries();

console.log('release draft restore regressions: ok');
