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
assert 'validate-installed' in needs, 'Production bypasses installed plugin and actual provider verification'
installed = deploy['jobs']['validate-installed']
assert installed['needs'] == 'validate' and installed['uses'] == './.github/workflows/portal-live-qa.yml'
assert installed['secrets'] == 'inherit'
qa = yaml.load(Path('.github/workflows/portal-live-qa.yml').read_text(), Loader=UniqueMappingLoader)
assert 'workflow_call' in qa.get('on', qa.get(True, {})), 'Installed-stack QA is not callable'
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
assert '--enable' not in Path('.github/workflows/deploy.yml').read_text(), 'Ordinary deployment must preserve existing onboarding activation'
release_steps = deploy['jobs']['deploy']['steps']
assert release_steps[0]['with']['fetch-depth'] == 0, 'Release recovery requires the actual installed ancestor history'
release_script = release_steps[1]['run']
assert 'git merge-base --is-ancestor "${deployment_base}" "${GITHUB_SHA}"' in release_script
assert 'git cat-file -e "${deployment_base}^{commit}"' in release_script
assert release_script.index('deployment_base=$(ssh') < release_script.index('release_stage=candidate-upload')
release_diffs = [line for line in release_script.splitlines() if 'if git diff --name-only ' in line]
assert release_diffs and all('"${deployment_base}" "${GITHUB_SHA}"' in line for line in release_diffs), 'Failed prior releases may lose required integration installers'
assert 'prepare-portal-release.php\' prune' in release_script and 'release_stage=obsolete-module-cleanup' in release_script
print('Release recovery compares the verified installed ancestor and retains snapshot-protected obsolete source cleanup.')

# Credential alignment must run the same native suite even when PR events are unavailable.
demo_alignment = yaml.load(Path('.github/workflows/demo-key-alignment.yml').read_text(), Loader=UniqueMappingLoader)
assert demo_alignment['jobs']['validate']['uses'] == './.github/workflows/submission-checks.yml'
assert demo_alignment['jobs']['align']['needs'] == 'validate'
assert not demo_alignment['jobs']['align'].get('if') and not demo_alignment['jobs']['align'].get('continue-on-error')
print('Demo key alignment requires successful shared native validation.')
