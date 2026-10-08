import assert from 'node:assert/strict';
import {
	claimRapidHolder,
	normalizeHeaderLevels,
	resolveExternalTools,
} from '../src/js/rapid-runtime.js';

assert.deepEqual(normalizeHeaderLevels(undefined), [2, 3, 4]);
assert.deepEqual(normalizeHeaderLevels([]), [2, 3, 4]);
assert.deepEqual(normalizeHeaderLevels([1, 3, 5]), [1, 3, 5]);

const customTool = class CustomTool {};
assert.deepEqual(resolveExternalTools({}, { custom: { class: customTool } }), {
	custom: { class: customTool },
});
assert.deepEqual(resolveExternalTools({ pageId: 42 }, config => ({
	custom: { class: customTool, config: { pageId: config.pageId } },
})), {
	custom: { class: customTool, config: { pageId: 42 } },
});
assert.deepEqual(resolveExternalTools({}, null), {});
assert.deepEqual(resolveExternalTools({}, []), {});

const holder = { dataset: {} };
assert.equal(claimRapidHolder(holder), true);
assert.equal(claimRapidHolder(holder), false);
assert.equal(holder.dataset.rapidInit, '1');

console.log('Rapid editor runtime regression tests passed.');
