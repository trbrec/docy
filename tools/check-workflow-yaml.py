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
