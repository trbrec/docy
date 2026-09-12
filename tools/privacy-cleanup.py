"""One-off authorized removal of exposed production diagnostics. Never emit log contents."""
import io,json,os,re,time,urllib.request,urllib.error,zipfile,concurrent.futures
BASE='https://api.github.com/repos/trbrec/docy'
CUTOFF='2026-09-12T10:11:26Z'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs): return None
opener=urllib.request.build_opener(NoRedirect)
def api(path,method='GET'):
    req=urllib.request.Request(BASE+path,method=method,headers={'Authorization':'Bearer '+os.environ['GH_TOKEN'],'Accept':'application/vnd.github+json','X-GitHub-Api-Version':'2022-11-28'})
    try:
        with opener.open(req,timeout=30) as response:return response.status,response.headers,response.read()
    except urllib.error.HTTPError as error:return error.code,error.headers,b''
def data(path):
    status,_,body=api(path)
    if status!=200:raise RuntimeError('Metadata request failed, status '+str(status))
    return json.loads(body)
def all_pages(path,key):
    out=[]
    for page in range(1,101):
        rows=data(path+('&' if '?' in path else '?')+'per_page=100&page='+str(page))[key];out+=rows
        if len(rows)<100:return out
    raise RuntimeError('Pagination limit exceeded')
# Scope to historical production deployments. Never delete source, releases, CRM records or CI checks.
runs=[r for r in all_pages('/actions/runs','workflow_runs') if r['path']=='.github/workflows/deploy.yml' and r['event']=='push' and r['status']=='completed' and r['created_at']<CUTOFF]
pattern=re.compile(r'(?:CRM_(?:REVIEW_VERIFIED|LIVE_RIGHTS_SNAPSHOT|LAYOUT|IDENTITY)|GREED_[A-Z_]+|ACR_RECOVERY_RESULT|FILE_RETRY_LIVE|RELEASE_AUDIT)[^\n]*\{|"(?:copyright_findings|user_email|file_hash|artist_credit|portal_post_meta|contract_state|received_images)"\s*:')
def inspect(run):
    code,headers,_=api('/actions/runs/'+str(run['id'])+'/logs')
    if code in (404,410):return run['id'],'absent'
    if code!=302:raise RuntimeError('Log availability check failed: '+str(code))
    # Signed archive URL receives no GitHub authorization header.
    url=headers.get('Location','')
    if not url.startswith('https://'):raise RuntimeError('Invalid archive redirect')
    with urllib.request.urlopen(url,timeout=45) as response:raw=response.read(25000001)
    if len(raw)>25000000:raise RuntimeError('Archive too large to inspect safely')
    with zipfile.ZipFile(io.BytesIO(raw)) as archive:
        if sum(i.file_size for i in archive.infolist())>100000000:raise RuntimeError('Expanded archive too large')
        found=any(pattern.search(archive.read(name).decode('utf-8','replace')) for name in archive.namelist() if not name.endswith('/'))
    return run['id'],'sensitive' if found else 'clear'
print('Historical production log inspection started.',flush=True)
results=[]
with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
    for result in pool.map(inspect,runs):results.append(result)
exposed=[rid for rid,status in results if status=='sensitive'];absent=sum(status=='absent' for _,status in results)
print('Inspection complete: '+json.dumps({'reviewed':len(results),'requires_removal':len(exposed),'already_absent':absent}),flush=True)
for rid in exposed:
    status,_,_=api('/actions/runs/'+str(rid)+'/logs','DELETE')
    if status not in (204,404,410):raise RuntimeError('Log removal failed: '+str(status))
    time.sleep(0.85)
    status,_,_=api('/actions/runs/'+str(rid)+'/logs')
    if status not in (404,410):raise RuntimeError('Deleted log remains downloadable')
    print('REMOVED_AND_VERIFIED run='+str(rid),flush=True)
ids={r['id'] for r in runs};removed_artifacts=0;unclassified_artifacts=0
for artifact in all_pages('/actions/artifacts','artifacts'):
    rid=artifact.get('workflow_run',{}).get('id')
    if rid not in ids:continue
    if rid in exposed or re.search(r'diagnostic|audit|log|debug|report|probe|snapshot',artifact['name'],re.I):
        status,_,_=api('/actions/artifacts/'+str(artifact['id']),'DELETE')
        if status not in (204,404):raise RuntimeError('Artifact removal failed')
        status,_,_=api('/actions/artifacts/'+str(artifact['id']))
        if status!=404:raise RuntimeError('Artifact remains accessible')
        removed_artifacts+=1;time.sleep(0.85)
    elif not artifact.get('expired'):unclassified_artifacts+=1
summary={'reviewed_runs':len(results),'removed_logs':len(exposed),'removed_artifacts':removed_artifacts,'unclassified_artifacts':unclassified_artifacts,'verified_unavailable':True}
print('PRIVACY_CLEANUP_RESULT '+json.dumps(summary),flush=True)
if unclassified_artifacts:raise RuntimeError('Remaining artifacts require private review')
