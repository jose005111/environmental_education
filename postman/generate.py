import json
import re
import sys
from collections import OrderedDict
from pathlib import Path

source = Path(sys.argv[1]) if len(sys.argv) > 1 else Path('openapi.json')
spec = json.loads(source.read_text())
components = spec.get('components', {}).get('schemas', {})


def dereference(schema):
    if not isinstance(schema, dict):
        return {}
    if '$ref' in schema:
        name = schema['$ref'].split('/')[-1]
        return dereference(components.get(name, {}))
    if 'allOf' in schema:
        merged = {}
        for part in schema['allOf']:
            merged.update(dereference(part))
        merged.update({k: v for k, v in schema.items() if k != 'allOf'})
        return merged
    return schema


def sample(schema, name='valor'):
    schema = dereference(schema)
    if 'example' in schema:
        return schema['example']
    if 'const' in schema:
        return schema['const']
    if schema.get('enum'):
        return schema['enum'][0]

    types = schema.get('type')
    if isinstance(types, list):
        types = next((item for item in types if item != 'null'), 'string')
    types = types or 'string'

    if types == 'object':
        properties = schema.get('properties', {})
        required = set(schema.get('required', properties.keys()))
        return {key: sample(value, key) for key, value in properties.items() if key in required}

    if types == 'array':
        item = schema.get('items', {})
        if dereference(item).get('format') == 'binary':
            return []
        return [sample(item, name)]

    if types == 'integer':
        return 1
    if types == 'number':
        return 1.0
    if types == 'boolean':
        return False

    fmt = schema.get('format')
    if fmt == 'date-time':
        return '2026-12-10T09:00:00Z'
    if fmt == 'date':
        return '2026-12-10'
    if fmt == 'email':
        return 'usuario@example.test'
    if fmt == 'binary':
        return '<arquivo>'
    if fmt == 'uuid':
        return '00000000-0000-0000-0000-000000000000'
    return name if name != 'valor' else 'texto'


def humanize(operation_id):
    suffix = operation_id.split('.', 1)[-1] if operation_id else 'operacao'
    return re.sub(r'[._-]+', ' ', suffix).title()


def parameter_sample(parameter):
    schema = dereference(parameter.get('schema', {}))
    value = sample(schema, parameter.get('name', 'valor'))
    return '' if isinstance(value, (dict, list)) else str(value)


def build_request(method, path, operation):
    raw_path = path.replace('{', ':').replace('}', '')
    url = {
        'raw': '{{baseUrl}}' + raw_path,
        'host': ['{{baseUrl}}'],
        'path': [segment for segment in raw_path.strip('/').split('/') if segment],
    }

    variables = []
    query = []
    headers = []

    for parameter in operation.get('parameters', []):
        location = parameter.get('in')
        name = parameter.get('name', '')
        if location == 'path':
            variables.append({'key': name, 'value': parameter_sample(parameter), 'description': parameter.get('description', '')})
        elif location == 'query':
            query.append({
                'key': name,
                'value': parameter_sample(parameter),
                'description': parameter.get('description', ''),
                'disabled': False,
            })

    if query:
        url['query'] = query
    if variables:
        url['variable'] = variables

    headers.append({'key': 'Accept', 'value': 'application/json'})

    body = None
    content = operation.get('requestBody', {}).get('content', {})
    if 'application/json' in content:
        headers.append({'key': 'Content-Type', 'value': 'application/json'})
        schema = content['application/json'].get('schema', {})
        body = {
            'mode': 'raw',
            'raw': json.dumps(sample(schema), ensure_ascii=False, indent=2),
            'options': {'raw': {'language': 'json'}},
        }
    elif 'multipart/form-data' in content:
        schema = dereference(content['multipart/form-data'].get('schema', {}))
        formdata = []
        for name, item_schema in schema.get('properties', {}).items():
            resolved = dereference(item_schema)
            if resolved.get('format') == 'binary' or (
                resolved.get('type') == 'array' and dereference(resolved.get('items', {})).get('format') == 'binary'
            ):
                formdata.append({'key': name, 'type': 'file', 'src': [], 'disabled': False})
            else:
                value = sample(item_schema, name)
                formdata.append({
                    'key': name,
                    'type': 'text',
                    'value': json.dumps(value, ensure_ascii=False) if isinstance(value, (dict, list)) else str(value),
                    'disabled': False,
                })
        body = {'mode': 'formdata', 'formdata': formdata}

    request = {
        'method': method.upper(),
        'header': headers,
        'url': url,
        'description': operation.get('description', ''),
    }
    if body:
        request['body'] = body

    item = {
        'name': humanize(operation.get('operationId')),
        'request': request,
        'response': [],
    }

    if operation.get('operationId') == 'v1.auth.login':
        item['event'] = [{
            'listen': 'test',
            'disabled': False,
            'script': {
                'type': 'text/javascript',
                'exec': [
                    'if (pm.response.code === 200) {',
                    "    const token = pm.response.json().token;",
                    "    if (token) pm.collectionVariables.set('token', token);",
                    '}',
                ],
            },
        }]

    return item


folders = OrderedDict()
for path, path_item in spec.get('paths', {}).items():
    for method, operation in path_item.items():
        if method not in {'get', 'post', 'put', 'patch', 'delete'}:
            continue
        tag = (operation.get('tags') or ['Outros'])[0]
        folders.setdefault(tag, []).append(build_request(method, path, operation))

collection = {
    'info': {
        'name': 'Ambiente API',
        'description': 'Coleção gerada a partir do OpenAPI da API da Plataforma Digital para Educação Ambiental em Comunidades. '
                       'Faça login para preencher automaticamente a variável token.',
        'schema': 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    },
    'auth': {
        'type': 'bearer',
        'bearer': [{'key': 'token', 'value': '{{token}}', 'type': 'string'}],
    },
    'variable': [
        {'key': 'baseUrl', 'value': 'http://127.0.0.1:8000/api/v1', 'type': 'string'},
        {'key': 'token', 'value': '', 'type': 'string'},
    ],
    'item': [{'name': name, 'item': items} for name, items in folders.items()],
}

target = Path('postman/ambiente-api.postman_collection.json')
target.parent.mkdir(parents=True, exist_ok=True)
target.write_text(json.dumps(collection, ensure_ascii=False, indent=2) + '\n')
