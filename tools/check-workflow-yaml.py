"""Parse every workflow with a safe loader and reject duplicate mapping keys."""
from pathlib import Path
import yaml

class UniqueMappingLoader(yaml.SafeLoader):
    pass

def unique_mapping(loader, node, deep=False):
    mapping = {}
    for key_node, value_node in node.value:
        key = loader.construct_object(key_node, deep=deep)
        if key in mapping:
            raise yaml.constructor.ConstructorError(
                "while constructing a mapping", node.start_mark,
                f"duplicate key {key!r}", key_node.start_mark,
            )
        mapping[key] = loader.construct_object(value_node, deep=deep)
    return mapping

UniqueMappingLoader.add_constructor(
    yaml.resolver.BaseResolver.DEFAULT_MAPPING_TAG, unique_mapping
)
paths = sorted(Path(".github/workflows").glob("*.yml"))
assert paths, "Workflow inventory is empty"
for path in paths:
    data = yaml.load(path.read_text(), Loader=UniqueMappingLoader)
    assert isinstance(data, dict) and isinstance(data.get("jobs"), dict), f"{path}: jobs missing"
    print(f"Workflow YAML valid: {path.name}")

# Validate the actual dependency graph, not the presence of a test name in comments.
deploy = yaml.load(Path('.github/workflows/deploy.yml').read_text(), Loader=UniqueMappingLoader)
validation = yaml.load(Path('.github/workflows/submission-checks.yml').read_text(), Loader=UniqueMappingLoader)
assert deploy['jobs']['validate']['uses'] == './.github/workflows/submission-checks.yml'
needs = deploy['jobs']['deploy']['needs']
assert 'validate' in ([needs] if isinstance(needs, str) else needs), 'Production is not gated by shared validation'
assert deploy['jobs']['deploy'].get('if') == "github.event_name == 'push'", 'Production may bypass validation on another event'
assert 'workflow_call' in validation.get('on', validation.get(True, {})), 'Shared validation is not callable'
assert {'verify', 'mysql-profile'} <= validation['jobs'].keys(), 'Required regression or MySQL job missing'
for name in ('verify', 'mysql-profile'):
    job = validation['jobs'][name]
    assert not job.get('continue-on-error'), 'Required validation job may ignore failure'
    assert not job.get('if'), 'Required validation job may be skipped'
    for step in job['steps']:
        assert not step.get('continue-on-error'), 'Required validation step may ignore failure'
print('Production dependency graph requires shared regression and real MySQL validation.')
