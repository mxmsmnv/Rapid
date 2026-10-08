/**
 * Small, DOM-light helpers shared by the Rapid editor and its regression tests.
 */

export function normalizeHeaderLevels(levels) {
	return Array.isArray(levels) && levels.length ? levels : [2, 3, 4];
}

export function resolveExternalTools(config, registry) {
	if (!registry) return {};

	const tools = typeof registry === 'function' ? registry(config) : registry;
	if (!tools || typeof tools !== 'object' || Array.isArray(tools)) return {};

	return tools;
}

export function claimRapidHolder(holder) {
	if (!holder || holder.dataset.rapidInit) return false;
	holder.dataset.rapidInit = '1';
	return true;
}
