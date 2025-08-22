### Préambule

Ce projet est un peu complexe. À l'origine, il devait uniquement s'agir de mon portfolio. Je souhaitais y regrouper tous
mes projets personnels, comme en témoigne les premières maquettes et captures d'écran jointes ci-dessous.

Avant ce projet, je développais déjà un site internet devant répondre à ce besoin. Cependant, je l'avais basé sur mon
framework maison, [VBcms 2](https://github.com/SofianeLasri/VBcms-2.0). L'inconvéniant de ce choix était que je devais
implémenter moi-même toutes les nouvelles fonctionnalités que je souhaitais ajouter sur le framework. Le développement
était donc très lent et fastidieux, en plus d'être épuisant sur le long terme. J'ai donc profité de ma première
alternance pour faire une pause totale sur son développement, afin de prendre le temps d'apprendre de nouvelles choses
au travail, mais aussi afin de refaire un nouveau design plus élégant que jamais.

Le développement du site a repris durant l'été 2022, où je me suis activement remis à créer de nouvelles maquettes.
Puis, j'ai recommencé le projet de zéro en me basant cette fois-ci sur le framework Laravel, utilisé par l'entreprise
qui m'avait embauché (Kang). Toutefois, la création de maquettes prend du temps. C'est également la première fois où j'
ai utilisé de vrais outils modernes pour le maquettage, testant d'abord Adobe XD, puis préférant Figma par la suite.

### Pourquoi faire simple quand on peut faire compliqué ?

Le projet Laravel étant désormais créé et en bonne voie, je me suis dit qu'il serait une bonne idée de créer un panneau
d'administration qui pourrait me permettre de gérer le contenu du site.

Car c'est vrai que sur VBcms 2, je n'avais plus vraiment à me soucier de cette partie. Le framework, s'apparentant d'
avantage à un cms, me permettait de gérer le contenu de manière très simple depuis l'interface d'administration. Pas
d'authentification à gérer, système de permission embarqué et surtout pas d'interface admin à développer, tout était
déjà prêt (ou presque).

Seulement, cela n'était pas le cas sur Laravel (et puis de toute façon je n'étais pas intéressé par les maquettes et
projets template). Il a donc fallu créer tout cela. C'est à ce moment que j'ai découvert l'éco système Laravel,
permettant assez simplement d'implémenter des fonctionnalités complexes à développer soi-même.

### Une partie administration au développement interminable

Si la partie vitrine/publique du site internet a été quasiment terminée en mars 2023, la partie admin, elle, ne l'a
jamais été (jusqu'à l'arrêt du projet le 15 juin 2024). En effet, souhaitant absolument réaliser le projet parfait, j'ai
passé un temps fou à développer les différentes fonctionnalités de l'interface admin. De plus, ne pouvant plus
travailler autant qu'auparavant sur VBcms 2 en raison de mon alternance, les temps de développement ont littéralement
explosé.

Par exemple, il m'a fallu plusieurs mois pour simplement rendre fonctionnelle l'authentification sur le projet avec les
différents sous domaines liés (quelque chose que ne supporte pas nativement Laravel). J'ai également passé un temps fou
à développer la médiathèque, qui devait permettre d'envoyer des fichiers sur le site afin de les utiliser pour la
création d'articles de blogs, ou encore de pages de projets.

Toutefois, je ne regrette pas le temps passé dessus. Ce projet m'a été bénéfique sur bien des aspects.

### Un projet riche en enseignements

Tout d'abord, c'est le premier projet que j'ai réalisé en utilisant un framework. J'ai appris énormément de choses, et
cela m'a permis de me forger une réelle expérience sur Laravel. Ayant touché à pratiquement tous les aspects du
framework, je connais désormais l'ensemble des fonctionnalités qu'il propose.

Enfin, le travail effectué sur ce projet n'est pas entièrement à jeter. En effet, à l'avenir je réutiliserai l'interface
admin que j'ai développée pour d'autres projets. Son design système est assez complet et permet de réaliser toutes
sortes d'interfaces.

### Liens

- Dépôt GitHub : <https://github.com/SofianeLasri/SL-Projects-Website>
- Maquette Figma (
  Vitrine) : [https://www.figma.com/design/...](https://www.figma.com/design/MHphZL3q3WvFzRsWdKh4ly/Site-internet)
- Maquette Figma (
  Dashboard) : [https://www.figma.com/design/...](https://www.figma.com/design/v1QLtDeZhDOC0b85crsVx8/Dashboard)