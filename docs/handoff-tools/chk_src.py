import re,sys,importlib,collections,json
mod,var,guide=sys.argv[1],sys.argv[2],sys.argv[3]
M=importlib.import_module(mod); L=getattr(M,var)
norm=lambda s: re.sub(r'[^a-z0-9]+','',s.lower())
g=norm(open(guide,encoding='utf-8').read())
# remove page header/footer noise lines inside the guide text so wrapped quotes match across pages
g=re.sub(r'\[p\d+\][^\n]*? created by - mymerchantnavy\.com page \d+ of \d+','',g)
bad=0
for i,q in enumerate(L,1):
    src=q[int(sys.argv[4])]
    if src and norm(src) not in g: bad+=1; print('NOT FOUND',i,src[:90])
print('questions',len(L),'| sources not found in the guide:',bad)
print(collections.Counter(q[int(sys.argv[5])] for q in L))
