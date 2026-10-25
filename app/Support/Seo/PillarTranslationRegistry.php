<?php

namespace App\Support\Seo;

/**
 * Human-reviewed English and Arabic copy for the stable public pillar pages.
 * Route paths and the canonical Persian source remain owned by PillarRegistry.
 */
final class PillarTranslationRegistry
{
    public static function get(string $key, string $locale): array
    {
        return self::all()[$locale][$key] ?? [];
    }

    private static function all(): array
    {
        return [
            'en' => [
                'economy' => [
                    'eyebrow' => 'EarthCoop economic model',
                    'title' => 'What Is the People’s Free Economy?',
                    'meta_title' => 'What Is the People’s Free Economy? | EarthCoop Economic Model',
                    'description' => 'The People’s Free Economy is EarthCoop’s proposed economic model: freedom to produce, own and exchange alongside common rights in resources, transparency, fair competition and opposition to monopolies.',
                    'summary' => 'EarthCoop proposes a fair framework that brings economic freedom and legitimate private ownership together with common rights in land and resources, transparency, responsibility and safeguards against monopoly.',
                    'breadcrumbs' => [
                        ['name' => 'Home', 'path' => '/'],
                        ['name' => 'People’s Free Economy', 'path' => '/economy'],
                    ],
                    'sections' => [
                        ['heading' => 'Economy in the service of justice—not the other way around', 'paragraphs' => [
                            'In this model, growth, profit and markets are not ultimate ends. The economy is a means to support human dignity, lasting well-being, participation, responsible freedom and the protection of the living environment.',
                            'The starting point is to recognise two rights together: a person’s right to their labour and legitimate property, and everyone’s common right in the land and fundamental resources that sustain life. Neither right is used to erase the other.',
                        ]],
                        ['heading' => 'Economic freedom with responsibility', 'paragraphs' => [
                            'People may produce, start businesses, invest, exchange goods and services, and own the legitimate results of their efforts. Economic freedom, however, is not a licence for monopoly, corruption, exploitation or cost-free appropriation of common resources.',
                        ], 'bullets' => [
                            'Respect for legitimate private ownership of the fruits of labour, production, creativity and responsible investment',
                            'Fair competition and opposition to monopoly and unjustified concentration of economic power',
                            'Respect for common rights when common resources are used exclusively',
                            'Transparency, auditing and the ability to scrutinise public economic flows',
                            'Giving priority to the creation of real value over destructive speculation',
                        ]],
                        ['heading' => 'Neither a state-run economy nor an unrestricted market', 'paragraphs' => [
                            'The People’s Free Economy is neither a state-run economy nor capitalism detached from public responsibility or an unrestricted free market. Its distinguishing feature is that economic freedom operates within a framework of rights, justice, transparency and anti-monopoly safeguards.',
                            'EarthCoop presents this as its own proposed model, not as a theory already accepted by every legal or economic system.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'Glass Economy: transparent and secure', 'path' => '/economy/glass'],
                        ['label' => 'Ownership and common entitlement', 'path' => '/economy/ownership'],
                        ['label' => 'The Earth and common resources', 'path' => '/commons'],
                        ['label' => 'Justice at EarthCoop', 'path' => '/justice'],
                    ],
                    'docs' => [
                        ['label' => 'EarthCoop Economic Law', 'url' => 'https://docs.earthcoop.ir/documents/econ/'],
                        ['label' => 'EarthCoop Constitution', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                    ],
                ],
                'cooperative' => [
                    'eyebrow' => 'EarthCoop cooperative and participatory identity',
                    'title' => 'Modern Cooperation: From Working Together to Ownership, Participation and Oversight',
                    'meta_title' => 'What Is Modern and Digital Cooperation? | EarthCoop',
                    'description' => 'At EarthCoop, modern cooperation connects working together with participation rights, oversight, legitimate ownership, transparency and digital organisation—beyond temporary or purely commercial collaboration.',
                    'summary' => 'Cooperation deepens when people are not merely consumers or users, but have a meaningful share in rights, responsibilities, decisions and oversight. EarthCoop pursues this approach from local communities to a global network.',
                    'breadcrumbs' => [
                        ['name' => 'Home', 'path' => '/'],
                        ['name' => 'Modern Cooperation', 'path' => '/cooperative'],
                    ],
                    'sections' => [
                        ['heading' => 'Cooperation is more than temporary collaboration', 'paragraphs' => [
                            'Collaboration can be formed for a short-term project, but lasting cooperation requires clear rules, member rights, shared responsibility, accountability and ways to participate in decisions.',
                            'EarthCoop seeks to bring these elements together in a digital, multi-level structure.',
                        ]],
                        ['heading' => 'How this relates to digital and platform cooperatives', 'paragraphs' => [
                            '“Digital cooperative” and “platform cooperative” are external terms for models that connect technology with member ownership or governance. They are useful points of comparison with EarthCoop, but EarthCoop is not presented as fully equivalent to either framework.',
                        ]],
                        ['heading' => 'From the right to participate to the right to oversee', 'paragraphs' => [
                            'In EarthCoop’s approach, participation means more than having an account on a platform or buying a share. Within their role and level, members should be able to take part in discussion, proposals, selection, oversight and evaluation of decisions.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'Global cooperation', 'path' => '/cooperative/global'],
                        ['label' => 'Participatory governance', 'path' => '/governance'],
                        ['label' => 'People’s Free Economy', 'path' => '/economy'],
                    ],
                    'docs' => [
                        ['label' => 'EarthCoop Constitution', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                        ['label' => 'EarthCoop Charter', 'url' => 'https://docs.earthcoop.ir/documents/ch/'],
                    ],
                ],
                'governance' => [
                    'eyebrow' => 'EarthCoop governance',
                    'title' => 'Democratic and Participatory Governance at EarthCoop',
                    'meta_title' => 'Democratic and Participatory Governance | EarthCoop',
                    'description' => 'EarthCoop’s participatory governance is built on participation, oversight, accountability, elections, transparency and connections between local communities and higher levels.',
                    'summary' => 'At EarthCoop, common rights are incomplete without meaningful participation and oversight. Governance should enable members to help shape decisions, make selections, oversee institutions and seek correction—from their nearest local community to higher levels.',
                    'breadcrumbs' => [
                        ['name' => 'Home', 'path' => '/'],
                        ['name' => 'Participatory Governance', 'path' => '/governance'],
                    ],
                    'sections' => [
                        ['heading' => 'A chain of participation, oversight and accountability', 'paragraphs' => [
                            'The underlying principle is that participation without oversight is vulnerable; oversight without transparency is ineffective; and transparency without a meaningful way to object and seek correction is insufficient.',
                            'For this reason, EarthCoop governance is not limited to voting. It also includes proposals, discussion, selection, oversight, reporting and routes for correction.',
                        ]],
                        ['heading' => 'From local communities to the global level', 'paragraphs' => [
                            'The structure starts with the nearest local community and connects to higher levels through assemblies, upward representation and digital systems. A higher level should not unnecessarily displace legitimate local authority or experience.',
                        ]],
                        ['heading' => 'Limiting the concentration of power', 'paragraphs' => [
                            'Selection, accountability, inspection, recorded decisions and opportunities for review are intended to prevent responsibility from turning into power without accountability. Implementation details may evolve as the system matures, while accountability and limits on concentrated power remain stable principles.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'Continuous elections without candidates', 'path' => '/governance/elections'],
                        ['label' => 'Governance from neighbourhood to world', 'path' => '/governance/local-to-global'],
                        ['label' => 'Justice and fundamental rights', 'path' => '/justice'],
                    ],
                    'docs' => [
                        ['label' => 'EarthCoop Constitution', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                        ['label' => 'EarthCoop Executive Bylaws', 'url' => 'https://docs.earthcoop.ir/documents/ex/'],
                    ],
                ],
                'justice' => [
                    'eyebrow' => 'EarthCoop’s foundational ideas',
                    'title' => 'Justice, Rights, Freedom and Equality at EarthCoop',
                    'meta_title' => 'Justice, Responsible Freedom and Fundamental Equality | EarthCoop',
                    'description' => 'EarthCoop’s account of justice: everything in its proper place and every right with its rightful holder, grounded in human dignity, responsible freedom, fundamental equality and common rights in the Earth and its resources.',
                    'summary' => 'EarthCoop begins its account of justice with a clear criterion: everything in its proper place and every right with its rightful holder. In this view, the place of the Earth and common resources must first be clarified; freedom, ownership, equality and responsibility are then assessed in light of actual rights.',
                    'breadcrumbs' => [
                        ['name' => 'Home', 'path' => '/'],
                        ['name' => 'Justice and Rights', 'path' => '/justice'],
                    ],
                    'sections' => [
                        ['heading' => 'The Earth in its proper place', 'paragraphs' => [
                            'The Earth and the fundamental resources that sustain life are treated as matters of common rights in EarthCoop. This principle grounds participation, oversight, benefit and responsibility in relation to common resources.',
                            'This is a principle within EarthCoop’s own framework; it is not presented as a universal definition or a law accepted by every country.',
                        ]],
                        ['heading' => 'Responsible freedom', 'paragraphs' => [
                            'Freedom is a fundamental value, but one person’s freedom cannot be used to deprive others of their rights or to claim unlimited control over common rights. EarthCoop’s formal term is “responsible freedom”; “just freedom” may be used to explain this relationship between freedom and rights.',
                        ]],
                        ['heading' => 'Fundamental equality, not identical outcomes', 'paragraphs' => [
                            'All people are equal in their fundamental right in the Earth and common resources. Differences in wealth, expertise, citizenship, language, gender, religion or social position do not erase this underlying equality.',
                            'This does not mean that everyone has identical abilities, roles, assets or economic outcomes. The aim is equality in fundamental rights and a real opportunity to participate.',
                        ]],
                        ['heading' => 'Human dignity before the system', 'paragraphs' => [
                            'People must not be reduced to instruments of production, consumption, voting, data collection or profit. Markets, institutions and technology at EarthCoop should remain in service of human dignity.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'The Earth and common resources', 'path' => '/commons'],
                        ['label' => 'People’s Free Economy', 'path' => '/economy'],
                        ['label' => 'Participatory governance', 'path' => '/governance'],
                    ],
                    'docs' => [
                        ['label' => 'EarthCoop Charter', 'url' => 'https://docs.earthcoop.ir/documents/ch/'],
                        ['label' => 'EarthCoop Constitution', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                    ],
                ],
            ],
            'ar' => [
                'economy' => [
                    'eyebrow' => 'النموذج الاقتصادي في EarthCoop',
                    'title' => 'ما الاقتصاد الحرّ الشعبي؟',
                    'meta_title' => 'ما الاقتصاد الحرّ الشعبي؟ | النموذج الاقتصادي في EarthCoop',
                    'description' => 'الاقتصاد الحرّ الشعبي هو النموذج الاقتصادي المقترح من EarthCoop؛ إذ يجمع حرية الإنتاج والملكية والتبادل مع الحقوق المشتركة في الموارد والشفافية والمنافسة العادلة ومناهضة الاحتكار.',
                    'summary' => 'تقترح EarthCoop إطارًا عادلًا يجمع الحرية الاقتصادية والملكية الخاصة المشروعة مع الحقوق المشتركة في الأرض والموارد والشفافية والمسؤولية والضمانات المناهضة للاحتكار.',
                    'breadcrumbs' => [
                        ['name' => 'الرئيسية', 'path' => '/'],
                        ['name' => 'الاقتصاد الحرّ الشعبي', 'path' => '/economy'],
                    ],
                    'sections' => [
                        ['heading' => 'الاقتصاد في خدمة العدالة، لا العكس', 'paragraphs' => [
                            'في هذا النموذج، لا يُعدّ النمو والربح والسوق غايات نهائية. فالاقتصاد وسيلة لدعم كرامة الإنسان والرفاه المستدام والمشاركة والحرية المسؤولة وحماية البيئة الحية.',
                            'تنطلق الفكرة من الاعتراف بحقّين معًا: حق الإنسان في ثمرة عمله وممتلكاته المشروعة، والحقّ المشترك للجميع في الأرض والموارد الأساسية للحياة. ولا يُستخدم أيّ منهما لإلغاء الآخر.',
                        ]],
                        ['heading' => 'حرية اقتصادية مقرونة بالمسؤولية', 'paragraphs' => [
                            'يمكن للأفراد الإنتاج وإنشاء الأعمال والاستثمار والتبادل وامتلاك النتائج المشروعة لجهودهم. لكن الحرية الاقتصادية لا تبيح الاحتكار أو الفساد أو الاستغلال أو الاستحواذ على الموارد المشتركة من دون تحمّل كلفته.',
                        ], 'bullets' => [
                            'احترام الملكية الخاصة المشروعة لثمرة العمل والإنتاج والإبداع والاستثمار المسؤول',
                            'المنافسة العادلة ومناهضة الاحتكار والتركيز غير المبرر للقوة الاقتصادية',
                            'مراعاة الحقوق المشتركة عند الاستخدام الحصري للموارد المشتركة',
                            'الشفافية والتدقيق وإتاحة الرقابة على التدفقات الاقتصادية العامة',
                            'إعطاء الأولوية لخلق قيمة حقيقية بدلًا من المضاربة المدمرة',
                        ]],
                        ['heading' => 'ليس اقتصادًا تديره الدولة ولا سوقًا بلا قيود', 'paragraphs' => [
                            'الاقتصاد الحرّ الشعبي ليس اقتصادًا تديره الدولة، ولا رأسمالية منفصلة عن المسؤولية العامة، ولا سوقًا حرة بلا قيود. وما يميّزه هو وضع الحرية الاقتصادية ضمن إطار من الحقوق والعدالة والشفافية ومناهضة الاحتكار.',
                            'تقدّم EarthCoop هذا النموذج بوصفه اقتراحها الخاص، لا نظريةً مقبولة مسبقًا في جميع الأنظمة القانونية أو الاقتصادية.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'الاقتصاد الزجاجي: الشفافية والأمان', 'path' => '/economy/glass'],
                        ['label' => 'الملكية والاستحقاق المشترك', 'path' => '/economy/ownership'],
                        ['label' => 'الأرض والموارد المشتركة', 'path' => '/commons'],
                        ['label' => 'العدالة في EarthCoop', 'path' => '/justice'],
                    ],
                    'docs' => [
                        ['label' => 'قانون EarthCoop الاقتصادي', 'url' => 'https://docs.earthcoop.ir/documents/econ/'],
                        ['label' => 'دستور EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                    ],
                ],
                'cooperative' => [
                    'eyebrow' => 'الهوية التعاونية والتشاركية في EarthCoop',
                    'title' => 'التعاون الحديث: من العمل المشترك إلى الملكية والمشاركة والرقابة',
                    'meta_title' => 'ما التعاون الحديث والتعاون الرقمي؟ | EarthCoop',
                    'description' => 'يربط التعاون الحديث في EarthCoop العمل المشترك بحقوق المشاركة والرقابة والملكية المشروعة والشفافية والتنظيم الرقمي، متجاوزًا التعاون المؤقت أو التجاري البحت.',
                    'summary' => 'يتعمّق التعاون عندما لا يكون الناس مجرد مستهلكين أو مستخدمين، بل تكون لهم حصة فعلية في الحقوق والمسؤوليات والقرارات والرقابة. وتتبع EarthCoop هذا النهج من المجتمعات المحلية إلى شبكة عالمية.',
                    'breadcrumbs' => [
                        ['name' => 'الرئيسية', 'path' => '/'],
                        ['name' => 'التعاون الحديث', 'path' => '/cooperative'],
                    ],
                    'sections' => [
                        ['heading' => 'التعاون أكثر من عمل مشترك مؤقت', 'paragraphs' => [
                            'قد ينشأ العمل المشترك لمشروع قصير الأجل، أما التعاون المستدام فيحتاج إلى قواعد واضحة وحقوق للأعضاء ومسؤولية مشتركة ومساءلة وسبل للمشاركة في القرارات.',
                            'تسعى EarthCoop إلى جمع هذه العناصر في بنية رقمية متعددة المستويات.',
                        ]],
                        ['heading' => 'العلاقة بالتعاونيات الرقمية وتعاونيات المنصات', 'paragraphs' => [
                            'مصطلحا «التعاونية الرقمية» و«تعاونية المنصة» تعبيران مستخدمان خارج EarthCoop لنماذج تربط التكنولوجيا بملكية الأعضاء أو حوكمتهم. وهما مفيدان للمقارنة مع EarthCoop، لكن EarthCoop لا تُقدَّم بوصفها مطابقة تمامًا لأيّ من هذين الإطارين.',
                        ]],
                        ['heading' => 'من حق المشاركة إلى حق الرقابة', 'paragraphs' => [
                            'في نهج EarthCoop، لا تقتصر المشاركة على امتلاك حساب في منصة أو شراء حصة. ينبغي أن يتمكن العضو، ضمن دوره ومستواه، من المشاركة في النقاش وتقديم المقترحات والاختيار والرقابة وتقييم القرارات.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'التعاون العالمي', 'path' => '/cooperative/global'],
                        ['label' => 'الحوكمة التشاركية', 'path' => '/governance'],
                        ['label' => 'الاقتصاد الحرّ الشعبي', 'path' => '/economy'],
                    ],
                    'docs' => [
                        ['label' => 'دستور EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                        ['label' => 'ميثاق EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/ch/'],
                    ],
                ],
                'governance' => [
                    'eyebrow' => 'الحوكمة في EarthCoop',
                    'title' => 'الحوكمة الديمقراطية والتشاركية في EarthCoop',
                    'meta_title' => 'الحوكمة الديمقراطية والتشاركية | EarthCoop',
                    'description' => 'تقوم الحوكمة التشاركية في EarthCoop على المشاركة والرقابة والمساءلة والانتخابات والشفافية وربط المجتمعات المحلية بالمستويات الأعلى.',
                    'summary' => 'في EarthCoop، لا تكتمل الحقوق المشتركة من دون مشاركة ورقابة فعليتين. وينبغي أن تتيح الحوكمة للأعضاء الإسهام في صياغة القرارات والاختيار والرقابة والتصحيح، بدءًا من أقرب مجتمع محلي وصولًا إلى المستويات الأعلى.',
                    'breadcrumbs' => [
                        ['name' => 'الرئيسية', 'path' => '/'],
                        ['name' => 'الحوكمة التشاركية', 'path' => '/governance'],
                    ],
                    'sections' => [
                        ['heading' => 'سلسلة المشاركة والرقابة والمساءلة', 'paragraphs' => [
                            'المبدأ الأساسي هو أن المشاركة من دون رقابة تظل عرضة للمخاطر، والرقابة من دون شفافية غير فعالة، والشفافية من دون وسيلة حقيقية للاعتراض وطلب التصحيح غير كافية.',
                            'لذلك لا تقتصر الحوكمة في EarthCoop على التصويت، بل تشمل أيضًا تقديم المقترحات والنقاش والاختيار والرقابة والتقارير ومسارات التصحيح.',
                        ]],
                        ['heading' => 'من المجتمع المحلي إلى المستوى العالمي', 'paragraphs' => [
                            'تبدأ البنية من أقرب مجتمع محلي وتتصل بالمستويات الأعلى عبر الجمعيات والتمثيل التصاعدي والأنظمة الرقمية. ولا ينبغي للمستوى الأعلى أن ينتزع بلا مبرر الصلاحية المحلية المشروعة أو يتجاهل الخبرة المحلية.',
                        ]],
                        ['heading' => 'الحد من تركّز السلطة', 'paragraphs' => [
                            'يُراد من الاختيار والمساءلة والتفتيش وتوثيق القرارات وإتاحة مراجعتها منع تحوّل المسؤولية إلى سلطة لا تخضع للمساءلة. وقد تتطور تفاصيل التنفيذ مع نضج النظام، بينما يظل مبدأ المساءلة والحد من تركّز السلطة ثابتًا.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'انتخابات مستمرة بلا مرشحين', 'path' => '/governance/elections'],
                        ['label' => 'الحوكمة من الحيّ إلى العالم', 'path' => '/governance/local-to-global'],
                        ['label' => 'العدالة والحقوق الأساسية', 'path' => '/justice'],
                    ],
                    'docs' => [
                        ['label' => 'دستور EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                        ['label' => 'النظام التنفيذي الأساسي لـ EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/ex/'],
                    ],
                ],
                'justice' => [
                    'eyebrow' => 'الأفكار التأسيسية في EarthCoop',
                    'title' => 'العدالة والحقوق والحرية والمساواة في EarthCoop',
                    'meta_title' => 'العدالة والحرية المسؤولة والمساواة الأساسية | EarthCoop',
                    'description' => 'تصوّر EarthCoop للعدالة: وضع كل شيء في موضعه الصحيح وإيصال كل حق إلى صاحبه، استنادًا إلى كرامة الإنسان والحرية المسؤولة والمساواة الأساسية والحقوق المشتركة في الأرض ومواردها.',
                    'summary' => 'تبدأ EarthCoop تصورها للعدالة بمعيار واضح: وضع كل شيء في موضعه الصحيح وإيصال كل حق إلى صاحبه. ووفق هذا التصور، ينبغي أولًا توضيح مكانة الأرض والموارد المشتركة، ثم تقييم الحرية والملكية والمساواة والمسؤولية على أساس الحقوق الفعلية.',
                    'breadcrumbs' => [
                        ['name' => 'الرئيسية', 'path' => '/'],
                        ['name' => 'العدالة والحقوق', 'path' => '/justice'],
                    ],
                    'sections' => [
                        ['heading' => 'الأرض في موضعها الصحيح', 'paragraphs' => [
                            'تُعدّ الأرض والموارد الأساسية للحياة موضوعًا لحقوق مشتركة في إطار EarthCoop. ويشكّل هذا المبدأ أساسًا للمشاركة والرقابة والانتفاع والمسؤولية تجاه الموارد المشتركة.',
                            'هذا مبدأ ضمن إطار EarthCoop الخاص، ولا يُقدَّم بوصفه تعريفًا عالميًا أو قانونًا تقبله جميع الدول.',
                        ]],
                        ['heading' => 'الحرية المسؤولة', 'paragraphs' => [
                            'الحرية قيمة أساسية، لكن لا يجوز استخدام حرية شخص لحرمان الآخرين من حقوقهم أو للاستحواذ غير المحدود على الحقوق المشتركة. والمصطلح الرسمي لدى EarthCoop هو «الحرية المسؤولة»؛ ويمكن استخدام «الحرية العادلة» لشرح العلاقة نفسها بين الحرية والحقوق.',
                        ]],
                        ['heading' => 'المساواة الأساسية، لا تطابق النتائج', 'paragraphs' => [
                            'يتساوى جميع البشر في حقهم الأساسي في الأرض والموارد المشتركة. ولا تلغي الاختلافات في الثروة أو الخبرة أو المواطنة أو اللغة أو الجنس أو الدين أو المكانة الاجتماعية هذه المساواة الأصلية.',
                            'ولا يعني ذلك تطابق قدرات جميع الأفراد أو أدوارهم أو ممتلكاتهم أو نتائجهم الاقتصادية؛ فالغاية هي المساواة في الحقوق الأساسية وإتاحة فرصة حقيقية للمشاركة.',
                        ]],
                        ['heading' => 'كرامة الإنسان قبل النظام', 'paragraphs' => [
                            'يجب ألا يُختزل الإنسان إلى أداة للإنتاج أو الاستهلاك أو التصويت أو جمع البيانات أو الربح. وينبغي أن تظل الأسواق والمؤسسات والتكنولوجيا في EarthCoop في خدمة كرامة الإنسان.',
                        ]],
                    ],
                    'related' => [
                        ['label' => 'الأرض والموارد المشتركة', 'path' => '/commons'],
                        ['label' => 'الاقتصاد الحرّ الشعبي', 'path' => '/economy'],
                        ['label' => 'الحوكمة التشاركية', 'path' => '/governance'],
                    ],
                    'docs' => [
                        ['label' => 'ميثاق EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/ch/'],
                        ['label' => 'دستور EarthCoop', 'url' => 'https://docs.earthcoop.ir/documents/co/'],
                    ],
                ],
            ],
        ];
    }
}
