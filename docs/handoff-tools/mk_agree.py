import json
from agree_new import A as S
from build_ask import build_ask
rows=[]
for idx,(sec,d,stem,c,w,src,ask,steps,reasons,concept) in enumerate(S):
    rows.append(dict(stem=stem,c=c,w=w,d=d,ask=ask,src=src,steps=steps,reasons=reasons,concept=concept,sec=sec,order=(float(sec),idx)))
out=build_ask(rows,[0,1,2,3])
print(len(out),[sum(1 for o in out if o['correct']==k) for k in (1,2,3,4)])
json.dump({"test":46,"questions":out},open('new_46.json','w',encoding='utf-8'),ensure_ascii=False)
