import json, openpyxl, csv, warnings
warnings.filterwarnings('ignore')
R='raw/'
# Población 2026 — leer directo del archivo DANE
pob={}; names={}
ws=openpyxl.load_workbook('../inversion-potencial/PPED-AreaMun-2018-2042_VP.xlsx',read_only=True)['PobMunicipalxÁrea']
for r in ws.iter_rows(values_only=True):
    if r[0]=='50' and r[4]==2026 and r[5]=='Total': pob[r[2]]=r[6]; names[r[2]]=r[3]
assert len(pob)==29
pob_dep=None
wd=openpyxl.load_workbook('../inversion-potencial/PPED-AreaDep-2018-2050_VP.xlsx',read_only=True)
for s in wd.sheetnames:
    for r in wd[s].iter_rows(values_only=True):
        if r and r[0]=='50' and 2026 in r and 'Total' in r: pob_dep=r[-1]; break
    if pob_dep: break
print('pob dep',pob_dep,sum(pob.values()))
# NBI
wb=openpyxl.load_workbook(R+'CNPV-2018-NBI.xlsx',read_only=True)
nbi={r[0]+r[2]:round(r[4],2) for r in wb['Municipios'].iter_rows(values_only=True) if r[0]=='50' and r[2]}
nbi_dep=[r[2] for r in wb['Departamento'].iter_rows(values_only=True) if r[0]=='50'][0]
# IPM
wi=openpyxl.load_workbook(R+'anexo-censal-pobreza-municipal-2018.xlsx',read_only=True)['2_IPM Mpio y Dpt']
ipm={r[0]:r[2] for r in wi.iter_rows(values_only=True) if r[0] and str(r[0]).startswith('50') and len(str(r[0]))==5}
# Educación
men=json.load(open(R+'men_meta.json'))
edu={r['c_digo_municipio']:float(r['cobertura_neta']) for r in men if r['a_o']=='2024'}
edu23={r['c_digo_municipio']:float(r['cobertura_neta']) for r in men if r['a_o']=='2023'}
for d in (nbi,ipm,edu): assert set(d)==set(pob),(set(pob)^set(d))
codes=sorted(pob)
def vals(d): return [{'divipola':c,'municipio':names[c],'valor':d.get(c)} for c in codes]
capas=[
 dict(codigo='POB-2026',nombre='Población total proyectada',unidad='habitantes',periodo='2026',
  fuente='DANE — Proyecciones de población municipales por área 2018-2042 (base CNPV 2018, actualización post COVID-19, 30/07/2025)',
  fuente_url='https://www.dane.gov.co/index.php/estadisticas-por-tema/demografia-y-poblacion/proyecciones-de-poblacion',
  polaridad='neutral_alto_bueno',valor_departamental=pob_dep or sum(pob.values()),valores=vals(pob)),
 dict(codigo='NBI-2018',nombre='Personas con Necesidades Básicas Insatisfechas (NBI)',unidad='%',periodo='2018',
  fuente='DANE — CNPV 2018, Necesidades Básicas Insatisfechas (NBI) total municipal',
  fuente_url='https://www.dane.gov.co/files/censo2018/informacion-tecnica/CNPV-2018-NBI.xlsx',
  polaridad='alto_malo',valor_departamental=nbi_dep,valores=vals(nbi)),
 dict(codigo='IPM-2018',nombre='Índice de Pobreza Multidimensional (IPM) municipal',unidad='%',periodo='2018',
  fuente='DANE — Medida de pobreza multidimensional municipal de fuente censal 2018 (CNPV 2018)',
  fuente_url='https://www.dane.gov.co/files/investigaciones/condiciones_vida/pobreza/2018/informacion-censal/anexo-censal-pobreza-municipal-2018.xlsx',
  polaridad='alto_malo',valor_departamental=None,
  nota_departamental='El anexo censal municipal del DANE no publica el agregado departamental.',valores=vals(ipm)),
 dict(codigo='EDU-CN-2024',nombre='Cobertura neta en educación (transición a media)',unidad='%',periodo='2024',
  fuente='MinEducación — Estadísticas en educación preescolar, básica y media por municipio y por departamento (datos.gov.co)',
  fuente_url='https://www.datos.gov.co/d/nudc-7mev',fuente_url_departamental='https://www.datos.gov.co/d/ji8i-4anb',
  polaridad='alto_bueno',valor_departamental=90.29,
  nota='Valores >100 % ocurren cuando la matrícula de 5-16 años supera la población proyectada (flujos entre municipios).',
  valor_anterior_departamental=92.37,valores=vals(edu)),
]
# polaridad: población usa escala secuencial neutra; dejar 'alto_bueno' para compatibilidad
capas[0]['polaridad']='alto_bueno'; capas[0]['valor_anterior_departamental']=1156405; capas[0]['periodo_anterior']='2025'; capas[3]['periodo_anterior']='2023'; capas[0]['nota']='Capa descriptiva: la escala es neutra (no implica mejor/peor).'
for c in capas[3]['valores']: c['valor_anterior']=edu23.get(c['divipola'])
for c,d in zip(capas,(0,2,1,2)): c['decimales']=d
from shapely.geometry import shape, mapping
from shapely.geometry.polygon import orient
from shapely.geometry import MultiPolygon, Polygon
def rewind(fn):
    g=json.load(open(fn))
    for f in g['features']:
        gm=shape(f['geometry'])
        gm=orient(gm,sign=-1.0) if gm.geom_type=='Polygon' else MultiPolygon([orient(p,sign=-1.0) for p in gm.geoms])
        f['geometry']=mapping(gm)
    return g
out={'version':'fase1-2026-11-15','departamento':{'divipola':'50','nombre':'Meta'},'capas':capas}
json.dump(out,open('datos-mapa-inicio.json','w'),ensure_ascii=False,indent=1)
with open('datos-mapa-inicio.csv','w',newline='',encoding='utf-8-sig') as f:
    w=csv.writer(f); w.writerow(['divipola','municipio']+[c['codigo'] for c in capas])
    for i,c in enumerate(codes): w.writerow([c,names[c]]+[k['valores'][i]['valor'] for k in capas])
    w.writerow(['50','Meta (departamental)']+[k['valor_departamental'] for k in capas])
print('ok')

geo=rewind('meta-municipios.geojson'); co=rewind('colombia-departamentos-mini.geojson')
open('mockup/data.js','w').write('window.SIID_MAPA='+json.dumps(out,ensure_ascii=False)+';\nwindow.META_GEO='+json.dumps(geo,ensure_ascii=False)+';\nwindow.CO_GEO='+json.dumps(co)+';\n')
